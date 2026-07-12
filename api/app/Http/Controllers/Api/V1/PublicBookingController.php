<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\BookingService;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PublicBookingController extends Controller
{
    protected BookingService $bookingService;

    protected MidtransService $midtransService;

    public function __construct(BookingService $bookingService, MidtransService $midtransService)
    {
        $this->bookingService = $bookingService;
        $this->midtransService = $midtransService;
    }

    /**
     * Store a newly created booking in storage.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->createBooking($request->validated());

        // Create Midtrans transaction
        $snapToken = $this->midtransService->createSnapTransaction($booking);

        if (! $snapToken) {
            // Cancel booking and release slot immediately on Midtrans creation failure
            $booking->update([
                'status' => BookingStatus::CANCELLED,
            ]);

            return response()->json([
                'message' => 'Gagal menghubungkan ke gateway pembayaran.',
            ], 502);
        }

        /** @var Payment|null $payment */
        $payment = $booking->payment;

        return response()->json([
            'booking' => new BookingResource($booking),
            'payment' => [
                'order_id' => $payment ? $payment->order_id : null,
                'snap_token' => $snapToken,
            ],
        ], 201);
    }

    /**
     * Display the specified booking by code.
     */
    public function show(string $slug, string $code): JsonResponse
    {
        $booking = Booking::with(['court', 'payment'])->where('booking_code', $code)->first();

        if (! $booking) {
            abort(404, 'Booking tidak ditemukan.');
        }

        return response()->json([
            'booking' => new BookingResource($booking),
        ]);
    }

    /**
     * Pay / retrieve snap token again for an existing booking.
     */
    public function pay(string $slug, string $code): JsonResponse
    {
        $booking = Booking::with(['payment'])->where('booking_code', $code)->first();

        if (! $booking) {
            abort(404, 'Booking tidak ditemukan.');
        }

        if ($booking->payment_status === PaymentStatus::PAID || $booking->status === BookingStatus::CONFIRMED || $booking->status === BookingStatus::COMPLETED) {
            return response()->json([
                'message' => 'Booking sudah dibayar.',
            ], 409);
        }

        $expiresAt = $booking->expires_at;

        if ($expiresAt instanceof \DateTimeInterface && $expiresAt->isPast()) {
            return response()->json([
                'message' => 'Waktu pembayaran booking ini sudah kedaluwarsa.',
            ], 409);
        }

        // Check if there is an existing payment record with a snap token
        /** @var Payment|null $payment */
        $payment = $booking->payments()->where('transaction_status', 'pending')->latest()->first();

        if ($payment && $payment->snap_token) {
            $expiresAtStr = $expiresAt instanceof \DateTimeInterface ? $expiresAt->format('Y-m-d H:i:s') : null;

            return response()->json([
                'order_id' => $payment->order_id,
                'snap_token' => $payment->snap_token,
                'expires_at' => $expiresAtStr,
            ]);
        }

        // Generate new snap transaction if none exists
        $snapToken = $this->midtransService->createSnapTransaction($booking);

        if (! $snapToken) {
            return response()->json([
                'message' => 'Gagal menghubungkan ke gateway pembayaran.',
            ], 502);
        }

        /** @var Payment|null $payment */
        $payment = $booking->payment;
        $expiresAtStr = $expiresAt instanceof \DateTimeInterface ? $expiresAt->format('Y-m-d H:i:s') : null;

        return response()->json([
            'order_id' => $payment ? $payment->order_id : null,
            'snap_token' => $snapToken,
            'expires_at' => $expiresAtStr,
        ]);
    }

    /**
     * Check payment status (polling endpoint for frontend).
     */
    public function paymentStatus(string $slug, string $code): JsonResponse
    {
        $booking = Booking::where('booking_code', $code)->first();

        if (! $booking) {
            abort(404, 'Booking tidak ditemukan.');
        }

        return response()->json([
            'status' => $booking->status instanceof BookingStatus ? $booking->status->value : $booking->status,
            'payment_status' => $booking->payment_status instanceof PaymentStatus ? $booking->payment_status->value : $booking->payment_status,
        ]);
    }

    /**
     * Cancel booking (customer self-cancellation within window)
     */
    public function cancel(string $slug, string $code): JsonResponse
    {
        $booking = Booking::with(['payment'])->where('booking_code', $code)->first();

        if (! $booking) {
            abort(404, 'Booking tidak ditemukan.');
        }

        if ($booking->status === BookingStatus::CANCELLED) {
            return response()->json([
                'message' => 'Booking sudah dibatalkan.',
            ], 409);
        }

        $tenant = app(Tenant::class);
        $windowHours = $tenant->cancellation_window_hours ?? 24;

        // Calculate hours difference between booking start time and now
        $hoursDiff = now()->diffInHours($booking->start_time, false);

        if ($hoursDiff < $windowHours) {
            return response()->json([
                'message' => "Pembatalan hanya diperbolehkan maksimal {$windowHours} jam sebelum jadwal dimulai.",
            ], 422);
        }

        DB::transaction(function () use ($booking) {
            $booking->update([
                'status' => BookingStatus::CANCELLED,
            ]);

            // Refund policy check
            if ($booking->payment_status === PaymentStatus::PAID) {
                $booking->update([
                    'payment_status' => PaymentStatus::REFUNDED,
                ]);

                /** @var Payment|null $payment */
                $payment = $booking->payments()->where('transaction_status', 'settlement')->first();
                if ($payment) {
                    $payment->update([
                        'transaction_status' => 'refund',
                    ]);

                    $this->midtransService->refundTransaction($payment->order_id, $booking->price, 'Pembatalan pelanggan');
                }
            } else {
                $booking->update([
                    'payment_status' => PaymentStatus::FAILED,
                ]);
            }
        });

        return response()->json([
            'message' => 'Booking berhasil dibatalkan.',
            'booking' => new BookingResource($booking->fresh()),
        ]);
    }

    /**
     * Simulate a successful payment for local development/testing.
     */
    public function simulatePayment(string $slug, string $code): JsonResponse
    {
        $booking = Booking::with(['payments', 'court'])->where('booking_code', $code)->first();

        if (! $booking) {
            abort(404, 'Booking tidak ditemukan.');
        }

        if ($booking->payment_status === PaymentStatus::PAID) {
            return response()->json([
                'message' => 'Booking sudah lunas.',
            ]);
        }

        DB::transaction(function () use ($booking) {
            $payment = $booking->payments()->where('transaction_status', 'pending')->latest()->first();

            if (! $payment) {
                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'order_id' => $booking->booking_code . '-' . time(),
                    'gross_amount' => $booking->price,
                    'transaction_status' => 'pending',
                ]);
            }

            $payment->update([
                'transaction_status' => 'settlement',
                'payment_type' => 'qris',
                'transaction_id' => 'mock-tx-' . bin2hex(random_bytes(8)),
                'paid_at' => now(),
            ]);

            $booking->update([
                'status' => BookingStatus::CONFIRMED,
                'payment_status' => PaymentStatus::PAID,
            ]);

            // Dispatch notification
            \App\Jobs\SendBookingNotifications::dispatch($booking);
        });

        return response()->json([
            'message' => 'Simulasi pembayaran sukses berhasil diproses.',
            'booking' => new BookingResource($booking->fresh()),
        ]);
    }
}
