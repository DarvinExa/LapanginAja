<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production') ?? false;
        Config::$isSanitized = config('midtrans.is_sanitized') ?? true;
        Config::$is3ds = config('midtrans.is_3ds') ?? true;
    }

    /**
     * Create Snap Transaction token from Midtrans.
     */
    public function createSnapTransaction(Booking $booking): ?string
    {
        $court = $booking->court;
        if (! $court instanceof Court) {
            abort(500, 'Booking tidak memiliki lapangan.');
        }
        $courtName = $court->name;

        // 1. Generate unique order ID for Midtrans
        $orderId = $booking->booking_code.'-'.time();

        // 2. Prepare params
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $booking->price,
            ],
            'item_details' => [
                [
                    'id' => (string) $booking->court_id,
                    'price' => (int) $booking->price,
                    'quantity' => 1,
                    'name' => 'Booking: '.$courtName,
                ],
            ],
            'customer_details' => [
                'first_name' => $booking->customer_name,
                'email' => $booking->customer_email,
                'phone' => $booking->customer_phone,
            ],
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'duration' => $booking->tenant->hold_minutes ?? 15,
                'unit' => 'minute',
            ],
        ];

        $serverKey = config('midtrans.server_key');
        if (str_contains($serverKey, 'YOUR_SANDBOX_SERVER_KEY') || empty($serverKey)) {
            // DEMO MODE: Create a mock pending payment record
            Payment::create([
                'booking_id' => $booking->id,
                'order_id' => $orderId,
                'gross_amount' => $booking->price,
                'snap_token' => 'mock-snap-token-' . bin2hex(random_bytes(8)),
                'transaction_status' => 'pending',
            ]);
            return 'mock-snap-token-' . bin2hex(random_bytes(8));
        }

        try {
            // Get snap token
            $snapToken = Snap::getSnapToken($params);

            // Save payment record in DB
            Payment::create([
                'booking_id' => $booking->id,
                'order_id' => $orderId,
                'gross_amount' => $booking->price,
                'snap_token' => $snapToken,
                'transaction_status' => 'pending',
            ]);

            return $snapToken;
        } catch (\Exception $e) {
            logger()->error('Gagal membuat transaksi Midtrans: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Query Midtrans for the current status of a transaction (server-to-server).
     *
     * Dipakai sebagai sumber kebenaran saat polling status agar aplikasi tidak
     * sepenuhnya bergantung pada webhook, yang sering tidak sampai pada
     * lingkungan lokal tanpa tunnel publik.
     *
     * @return array<string, mixed>|null
     */
    public function checkTransactionStatus(string $orderId): ?array
    {
        $serverKey = config('midtrans.server_key');
        if (empty($serverKey) || str_contains($serverKey, 'YOUR_SANDBOX_SERVER_KEY')) {
            return null;
        }

        try {
            $status = Transaction::status($orderId);

            return json_decode(json_encode($status), true);
        } catch (\Throwable $e) {
            logger()->error("Gagal cek status transaksi Midtrans untuk order {$orderId}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Request a refund on Midtrans.
     */
    public function refundTransaction(string $orderId, float $amount, string $reason): bool
    {
        try {
            $params = [
                'refund_key' => 'ref-'.time(),
                'amount' => (int) $amount,
                'reason' => $reason,
            ];

            Transaction::refund($orderId, $params);

            return true;
        } catch (\Exception $e) {
            logger()->error("Gagal melakukan refund Midtrans untuk order {$orderId}: ".$e->getMessage());

            return false;
        }
    }
}
