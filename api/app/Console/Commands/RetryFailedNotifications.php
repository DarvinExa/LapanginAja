<?php

namespace App\Console\Commands;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Notification;
use App\Services\DocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class RetryFailedNotifications extends Command
{
    protected $signature = 'notifications:retry';

    protected $description = 'Retry sending failed email notifications';

    public function handle(DocumentService $documentService): void
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
            $booking = $notification->booking;
            if (! $booking) {
                continue;
            }

            if ($notification->channel !== NotificationChannel::EMAIL) {
                continue;
            }

            $notification->increment('retry_count');

            $this->info('Retrying Email to: '.$notification->recipient);
            try {
                $qrCode = $documentService->generateQrCode($booking);
                $pdfData = $documentService->generateInvoicePdf($booking, $qrCode);

                Mail::to($notification->recipient ?? $booking->customer_email)
                    ->send(new BookingConfirmedMail($booking, $qrCode, $pdfData));

                $notification->update(['status' => NotificationStatus::SENT]);
                $count++;
            } catch (\Exception $e) {
                $notification->update(['error_message' => $e->getMessage()]);
                logger()->error("Retry email failed for notification {$notification->id}: ".$e->getMessage());
            }
        }

        $this->info("Retried {$count} notifications successfully.");
    }
}
