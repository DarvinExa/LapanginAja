<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendBookingNotifications;
use App\Models\Booking;
use App\Models\Payment;
use App\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    /**
     * Handle Midtrans Payment Webhook notifications.
     */
    public function midtrans(Request $request): JsonResponse
    {
        $payload = $request->all();

        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $signatureKey = $payload['signature_key'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;

        if (! $orderId || ! $statusCode || ! $grossAmount || ! $signatureKey) {
            return response()->json(['message' => 'Payload tidak lengkap.'], 400);
        }

        // 1. Verify Midtrans Signature Key
        $serverKey = config('midtrans.server_key');
        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if ($signatureKey !== $expectedSignature) {
            logger()->warning('Midtrans signature verification failed.', ['payload' => $payload]);

            return response()->json(['message' => 'Signature tidak valid.'], 403);
        }

        // 2. Extract Booking Code and Find Booking (reconstruct code by removing the timestamp suffix)
        $parts = explode('-', $orderId);
        array_pop($parts);
        $bookingCode = implode('-', $parts);
        $booking = Booking::withoutGlobalScope(TenantScope::class)->where('booking_code', $bookingCode)->first();

        if (! $booking) {
            logger()->warning('Booking not found for webhook order_id.', ['order_id' => $orderId]);

            return response()->json(['message' => 'Booking tidak ditemukan.'], 404);
        }

        // 3. Validate Gross Amount (anti-manipulation)
        if (abs((float) $booking->price - (float) $grossAmount) > 0.01) {
            logger()->error('Gross amount mismatch in payment webhook.', [
                'booking_price' => $booking->price,
                'gross_amount' => $grossAmount,
                'order_id' => $orderId,
            ]);

            return response()->json(['message' => 'Nominal pembayaran tidak sesuai.'], 400);
        }

        // 4. DB Transaction for status update and idempotency check
        DB::transaction(function () use ($booking, $orderId, $transactionStatus, $fraudStatus, $payload) {
            // Lock the booking row for update
            /** @var Booking $booking */
            $booking = Booking::withoutGlobalScope(TenantScope::class)->where('id', $booking->id)->lockForUpdate()->first();

            // Find or create the payment record
            $payment = Payment::where('order_id', $orderId)->first();

            if (! $payment) {
                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'order_id' => $orderId,
                    'gross_amount' => $booking->price,
                    'transaction_status' => 'pending',
                ]);
            }

            // Idempotency: If payment is already in final success state, skip
            if ($payment->transaction_status === 'settlement') {
                return;
            }

            // Map transaction status to local enum states
            if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
                // Payment success
                $payment->update([
                    'transaction_status' => 'settlement',
                    'payment_type' => $payload['payment_type'] ?? null,
                    'transaction_id' => $payload['transaction_id'] ?? null,
                    'paid_at' => now(),
                    'raw_payload' => $payload,
                ]);

                $booking->update([
                    'status' => BookingStatus::CONFIRMED,
                    'payment_status' => PaymentStatus::PAID,
                ]);

                // Dispatch notification job
                SendBookingNotifications::dispatch($booking);
            } elseif (in_array($transactionStatus, ['expire', 'cancel', 'deny'])) {
                // Payment failed/cancelled
                $payment->update([
                    'transaction_status' => $transactionStatus,
                    'raw_payload' => $payload,
                ]);

                // Only cancel the booking if it is still pending
                // (Webhooks or scheduling race handling - do not cancel confirmed/paid bookings)
                if ($booking->status === BookingStatus::PENDING) {
                    $booking->update([
                        'status' => BookingStatus::CANCELLED,
                        'payment_status' => PaymentStatus::FAILED,
                    ]);
                }
            }
        });

        return response()->json(['message' => 'Webhook berhasil diproses.']);
    }
}
