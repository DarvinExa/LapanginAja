<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DocumentService
{
    /**
     * Generate base64 PNG QR Code of the booking code.
     */
    public function generateQrCode(Booking $booking): string
    {
        return base64_encode(
            QrCode::format('svg')
                ->size(150)
                ->margin(1)
                ->generate($booking->booking_code)
        );
    }

    /**
     * Generate raw PDF invoice binary string.
     */
    public function generateInvoicePdf(Booking $booking, string $qrCodeBase64): string
    {
        $pdf = Pdf::loadView('emails.invoice', [
            'booking' => $booking,
            'qrCode' => $qrCodeBase64,
        ]);

        return $pdf->output();
    }
}
