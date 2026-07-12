<?php

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Notification;
use App\Models\Tenant;
use App\Services\DocumentService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendBookingNotifications implements ShouldQueue
{
    use Queueable;

    public Booking $booking;

    /**
     * Create a new job instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * Execute the job.
     */
    public function handle(
        DocumentService $documentService,
        WhatsAppService $whatsappService
    ): void {
        $booking = Booking::with(['court', 'tenant'])->find($this->booking->id);

        if (! $booking) {
            return;
        }

        // 1. Generate QR Code and PDF Invoice
        $qrCode = $documentService->generateQrCode($booking);
        $pdfData = $documentService->generateInvoicePdf($booking, $qrCode);

        // 2. Send Email Mailable with PDF attachment
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
            Mail::to($booking->customer_email)->send(new BookingConfirmedMail($booking, $qrCode, $pdfData));
        } catch (\Exception $e) {
            logger()->error('Gagal mengirim email booking confirmed: '.$e->getMessage());
            $emailLog->update([
                'status' => NotificationStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }

        // 3. Send WhatsApp Notification
        /** @var Tenant $tenant */
        $tenant = $booking->tenant;
        /** @var Court $court */
        $court = $booking->court;

        $timezone = $tenant->timezone ?? 'Asia/Jakarta';
        $startTime = $booking->start_time;
        $endTime = $booking->end_time;

        $startTimeStr = $startTime instanceof \DateTimeInterface ? (new Carbon($startTime))->timezone($timezone)->format('d M Y, H:i') : $startTime;
        $endTimeStr = $endTime instanceof \DateTimeInterface ? (new Carbon($endTime))->timezone($timezone)->format('H:i') : $endTime;

        $whatsappMessage = "Halo {$booking->customer_name},\n\n"
            ."Pemesanan lapangan Anda di *{$tenant->name}* telah dikonfirmasi!\n\n"
            ."Detail Booking:\n"
            ."- Kode: *{$booking->booking_code}*\n"
            ."- Lapangan: {$court->name}\n"
            ."- Waktu: {$startTimeStr} - {$endTimeStr} ({$timezone})\n"
            .'- Total: Rp '.number_format($booking->price, 0, ',', '.')."\n\n"
            ."Silakan tunjukkan QR Code pada e-ticket/email Anda saat check-in di lapangan.\n\n"
            .'Terima kasih, LapanginAja!';

        $whatsappService->sendWhatsAppMessage($booking, $booking->customer_phone, $whatsappMessage);
    }
}
