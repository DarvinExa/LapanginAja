<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\Notification;

class WhatsAppService
{
    /**
     * Send a WhatsApp message mock.
     */
    public function sendWhatsAppMessage(Booking $booking, string $phone, string $messageText): bool
    {
        $type = str_contains($messageText, 'Pengingat') ? NotificationType::REMINDER : NotificationType::PAID;

        // 1. Prepare notification log in DB
        $notification = Notification::create([
            'booking_id' => $booking->id,
            'channel' => NotificationChannel::WHATSAPP,
            'type' => $type,
            'recipient' => $phone,
            'content' => $messageText,
            'status' => NotificationStatus::SENT,
            'retry_count' => 0,
        ]);

        try {
            $token = env('FONNTE_TOKEN');

            if (empty($token) || $token === 'your_fonnte_api_token') {
                // Mock Mode Fallback
                logger()->info("WhatsApp message sent (Mock Mode) to {$phone}: {$messageText}");
                return true;
            }

            // Real Fonnte API Request
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $token,
            ])->post('https://api.fonnte.com/send', [
                'target' => $phone,
                'message' => $messageText,
            ]);

            if ($response->successful()) {
                logger()->info("WhatsApp message sent (Real Fonnte) to {$phone}");
                return true;
            }

            throw new \Exception("Fonnte API error: " . $response->body());
        } catch (\Exception $e) {
            logger()->error("Gagal mengirim WhatsApp ke {$phone}: ".$e->getMessage());

            $notification->update([
                'status' => NotificationStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
