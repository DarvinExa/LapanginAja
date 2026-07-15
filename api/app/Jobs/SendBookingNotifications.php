<?php

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Notification;
use App\Services\DocumentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendBookingNotifications implements ShouldQueue
{
    use Queueable;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function handle(DocumentService $documentService): void
    {
        $booking = Booking::with(['court', 'tenant'])->find($this->booking->id);

        if (! $booking) {
            return;
        }

        $emailLog = Notification::create([
            'booking_id' => $booking->id,
            'channel' => NotificationChannel::EMAIL,
            'type' => NotificationType::ETICKET,
            'recipient' => $booking->customer_email,
            'content' => 'E-Ticket & Invoice Mail Sent',
            'status' => NotificationStatus::SENT,
            'retry_count' => 0,
        ]);

        try {
            $qrCode = $documentService->generateQrCode($booking);

            // Lampiran PDF opsional: bila gagal dibuat, email e-ticket tetap dikirim tanpa lampiran.
            $pdfData = null;
            try {
                $pdfData = $documentService->generateInvoicePdf($booking, $qrCode);
            } catch (\Throwable $e) {
                logger()->warning('Gagal membuat PDF invoice, email tetap dikirim tanpa lampiran: '.$e->getMessage(), [
                    'booking_code' => $booking->booking_code,
                ]);
            }

            Mail::to($booking->customer_email)->send(new BookingConfirmedMail($booking, $qrCode, $pdfData));
        } catch (\Throwable $e) {
            logger()->error('Gagal mengirim email booking confirmed: '.$e->getMessage());
            $emailLog->update([
                'status' => NotificationStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
