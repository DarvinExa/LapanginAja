<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:release-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically release pending bookings that have expired';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $expiredBookings = Booking::where('status', BookingStatus::PENDING)
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredBookings->isEmpty()) {
            $this->info('No expired bookings found.');

            return;
        }

        $count = 0;

        foreach ($expiredBookings as $booking) {
            DB::transaction(function () use ($booking, &$count) {
                // Lock the booking row to prevent race conditions with webhook confirmations
                /** @var Booking|null $lockedBooking */
                $lockedBooking = Booking::where('id', $booking->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedBooking && $lockedBooking->status === BookingStatus::PENDING) {
                    $lockedBooking->update([
                        'status' => BookingStatus::CANCELLED,
                        'payment_status' => PaymentStatus::FAILED,
                    ]);

                    // Cancel pending payments
                    $lockedBooking->payments()
                        ->where('transaction_status', 'pending')
                        ->update([
                            'transaction_status' => 'expire',
                        ]);

                    logger()->info('Auto-released expired booking: '.$lockedBooking->booking_code);
                    $count++;
                }
            });
        }

        $this->info("Released {$count} expired bookings.");
    }
}
