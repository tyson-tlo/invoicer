<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class GmailApiTransport implements TransportInterface
{
    public function __construct(private string $accessToken) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $raw = $message->toString();

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='),
            ]);

        if ($response->failed()) {
            throw new TransportException('Gmail API rejected the message: '.$response->body());
        }

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }
}
