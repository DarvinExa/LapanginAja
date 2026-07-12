<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Notification;
use App\Models\Tenant;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendBookingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send H-1 booking reminder notifications to customers';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        $tomorrow = Carbon::now()->addDay();
        $startUtc = $tomorrow->copy()->startOfDay()->utc();
        $endUtc = $tomorrow->copy()->endOfDay()->utc();

        $bookings = Booking::where('status', BookingStatus::CONFIRMED)
            ->whereBetween('start_time', [$startUtc, $endUtc])
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings found for tomorrow.');

            return;
        }

        $count = 0;

        foreach ($bookings as $booking) {
            $alreadySent = Notification::where('booking_id', $booking->id)
                ->where('type', 'whatsapp')
                ->where('content', 'like', '%Pengingat H-1%')
                ->exists();

            if ($alreadySent) {
                continue;
            }

            /** @var Tenant $tenant */
            $tenant = $booking->tenant;
            /** @var Court $court */
            $court = $booking->court;

            $timezone = $tenant->timezone ?? 'Asia/Jakarta';

            $startTime = $booking->start_time;
            $endTime = $booking->end_time;
            $startTimeStr = $startTime instanceof \DateTimeInterface ? (new Carbon($startTime))->timezone($timezone)->format('H:i') : $startTime;
            $endTimeStr = $endTime instanceof \DateTimeInterface ? (new Carbon($endTime))->timezone($timezone)->format('H:i') : $endTime;

            $message = "Halo {$booking->customer_name},\n\n"
                ."Ini adalah pengingat (*Pengingat H-1*) bahwa Anda memiliki jadwal main besok:\n"
                ."- Venue: *{$tenant->name}*\n"
                ."- Lapangan: {$court->name}\n"
                ."- Waktu: {$startTimeStr} - {$endTimeStr} ({$timezone})\n"
                ."- Kode Booking: *{$booking->booking_code}*\n\n"
                .'Harap datang 10 menit sebelum waktu bermain. Sampai jumpa!';

            $success = $whatsappService->sendWhatsAppMessage($booking, $booking->customer_phone, $message);
            if ($success) {
                $count++;
            }
        }

        $this->info("Sent {$count} reminders.");
    }
}
