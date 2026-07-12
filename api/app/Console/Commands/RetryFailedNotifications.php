<?php

namespace App\Console\Commands;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Notification;
use App\Services\DocumentService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class RetryFailedNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:retry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry sending failed notifications';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsappService, DocumentService $documentService): void
    {
        $failedNotifications = Notification::where('status', NotificationStatus::FAILED)
            ->where('retry_count', '<', 3)
            ->get();

        if ($failedNotifications->isEmpty()) {
            $this->info('No failed notifications to retry.');

            return;
        }

        $count = 0;

        foreach ($failedNotifications as $notification) {
            /** @var Booking|null $booking */
            $booking = $notification->booking;
            if (! $booking) {
                continue;
            }

            $notification->increment('retry_count');

            if ($notification->channel === NotificationChannel::WHATSAPP) {
                $this->info('Retrying WhatsApp to: '.$notification->recipient);
                $success = $whatsappService->sendWhatsAppMessage($booking, $notification->recipient ?? '', $notification->content ?? '');
                if ($success) {
                    $notification->update(['status' => NotificationStatus::SENT]);

                    // Clean up duplicate logs
                    Notification::where('booking_id', $booking->id)
                        ->where('channel', NotificationChannel::WHATSAPP)
                        ->latest('id')
                        ->first()
                        ?->delete();

                    $count++;
                }
            } elseif ($notification->channel === NotificationChannel::EMAIL) {
                $this->info('Retrying Email to: '.$notification->recipient);
                try {
                    $qrCode = $documentService->generateQrCode($booking);
                    $pdfData = $documentService->generateInvoicePdf($booking, $qrCode);

                    Mail::to($notification->recipient ?? $booking->customer_email)
                        ->send(new BookingConfirmedMail($booking, $qrCode, $pdfData));

                    $notification->update(['status' => NotificationStatus::SENT]);
                    $count++;
                } catch (\Exception $e) {
                    $notification->update([
                        'error_message' => $e->getMessage(),
                    ]);
                    logger()->error("Retry email failed for notification {$notification->id}: ".$e->getMessage());
                }
            }
        }

        $this->info("Retried {$count} notifications successfully.");
    }
}
