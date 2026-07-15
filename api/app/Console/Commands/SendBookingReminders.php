<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Send H-1 booking reminder emails to customers';

    public function handle(): void
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
                ->where('channel', NotificationChannel::EMAIL)
                ->where('type', NotificationType::REMINDER)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $tenant = $booking->tenant;
            $court = $booking->court;
            $timezone = $tenant->timezone ?? 'Asia/Jakarta';

            $startTime = $booking->start_time;
            $endTime = $booking->end_time;
            $startTimeStr = $startTime instanceof \DateTimeInterface ? (new Carbon($startTime))->timezone($timezone)->format('H:i') : $startTime;
            $endTimeStr = $endTime instanceof \DateTimeInterface ? (new Carbon($endTime))->timezone($timezone)->format('H:i') : $endTime;

            $subject = 'Pengingat H-1 Jadwal Main - LapanginAja';
            $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"></head>'
                .'<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
                .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px 16px;"><tr><td align="center">'
                .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;">'
                .'<tr><td style="font-size:20px;font-weight:800;color:#059669;padding:0 0 16px;">LapanginAja</td></tr>'
                .'<tr><td style="font-size:18px;font-weight:700;padding:0 0 12px;">Pengingat Jadwal Main Besok</td></tr>'
                .'<tr><td style="font-size:14px;line-height:1.6;color:#334155;padding:0 0 16px;">'
                ."Halo {$booking->customer_name}, ini pengingat bahwa Anda memiliki jadwal main besok."
                .'</td></tr>'
                .'<tr><td style="font-size:14px;line-height:1.8;color:#0f172a;padding:0 0 16px;">'
                ."Venue: <b>{$tenant->name}</b><br>"
                ."Lapangan: {$court->name}<br>"
                ."Waktu: {$startTimeStr} - {$endTimeStr} ({$timezone})<br>"
                ."Kode Booking: <b>{$booking->booking_code}</b>"
                .'</td></tr>'
                .'<tr><td style="font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;padding:16px 0 0;">Harap datang 10 menit sebelum waktu bermain. Sampai jumpa!</td></tr>'
                .'</table></td></tr></table></body></html>';

            $status = NotificationStatus::SENT;
            $errorMessage = null;

            try {
                Mail::html($html, function ($message) use ($booking, $subject) {
                    $message->to($booking->customer_email)->subject($subject);
                });
                $count++;
            } catch (\Throwable $e) {
                $status = NotificationStatus::FAILED;
                $errorMessage = $e->getMessage();
                logger()->error("Gagal mengirim reminder untuk booking {$booking->booking_code}: ".$e->getMessage());
            }

            Notification::create([
                'booking_id' => $booking->id,
                'channel' => NotificationChannel::EMAIL,
                'type' => NotificationType::REMINDER,
                'recipient' => $booking->customer_email,
                'content' => 'Pengingat H-1 jadwal main',
                'status' => $status,
                'error_message' => $errorMessage,
                'retry_count' => 0,
            ]);
        }

        $this->info("Sent {$count} reminders.");
    }
}
