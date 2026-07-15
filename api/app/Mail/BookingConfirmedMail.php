<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;

    public string $qrCode;

    protected ?string $pdfData;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking, string $qrCode, ?string $pdfData = null)
    {
        $this->booking = $booking;
        $this->qrCode = $qrCode;
        $this->pdfData = $pdfData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'E-Ticket & Invoice Pemesanan - '.$this->booking->booking_code,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.eticket',
            with: [
                'booking' => $this->booking,
                'qrCode' => $this->qrCode,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->pdfData === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdfData, 'invoice-'.$this->booking->booking_code.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
