<?php

namespace App\Mail\Transports;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoApiTransport extends AbstractTransport
{
    public function __toString(): string
    {
        return 'brevo+api';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('Brevo transport hanya mendukung pesan email MIME.');
        }

        $apiKey = (string) config('services.brevo.api_key');

        if ($apiKey === '') {
            throw new TransportException('BREVO_API_KEY belum dikonfigurasi.');
        }

        $from = $email->getFrom()[0] ?? new Address(
            (string) config('mail.from.address'),
            (string) config('mail.from.name')
        );

        $to = $this->formatAddresses($email->getTo());

        if ($to === []) {
            throw new TransportException('Email tujuan tidak tersedia.');
        }

        $payload = [
            'sender' => $this->formatAddress($from),
            'to' => $to,
            'subject' => $email->getSubject() ?: '(Tanpa subjek)',
        ];

        if ($email->getCc() !== []) {
            $payload['cc'] = $this->formatAddresses($email->getCc());
        }

        if ($email->getBcc() !== []) {
            $payload['bcc'] = $this->formatAddresses($email->getBcc());
        }

        if ($email->getReplyTo() !== []) {
            $payload['replyTo'] = $this->formatAddress($email->getReplyTo()[0]);
        }

        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = $email->getHtmlBody();
        }

        if ($email->getTextBody() !== null) {
            $payload['textContent'] = $email->getTextBody();
        }

        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            $payload['textContent'] = $email->getBody()?->bodyToString() ?: '';
        }

        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                'name' => $attachment->getFilename() ?: 'attachment',
                'content' => base64_encode($attachment->bodyToString()),
            ];
        }

        if ($attachments !== []) {
            $payload['attachment'] = $attachments;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['api-key' => $apiKey])
                ->timeout(30)
                ->retry(2, 500)
                ->post(
                    (string) config('services.brevo.endpoint'),
                    $payload
                );
        } catch (\Throwable $exception) {
            throw new TransportException(
                'Tidak dapat terhubung ke Brevo API: '.$exception->getMessage(),
                0,
                $exception
            );
        }

        if ($response->failed()) {
            throw new TransportException(
                'Brevo API menolak email (HTTP '.$response->status().'): '.$response->body()
            );
        }
    }

    /**
     * @param array<int, Address> $addresses
     * @return array<int, array{email: string, name?: string}>
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->formatAddress($address), $addresses);
    }

    /** @return array{email: string, name?: string} */
    private function formatAddress(Address $address): array
    {
        $formatted = ['email' => $address->getAddress()];

        if ($address->getName() !== '') {
            $formatted['name'] = $address->getName();
        }

        return $formatted;
    }
}
