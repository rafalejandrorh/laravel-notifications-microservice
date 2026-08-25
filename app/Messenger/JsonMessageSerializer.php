<?php

namespace App\Messenger;

use App\Exceptions\PermanentNotificationException;
use App\Message\SendEmailMessage;
use App\Message\SendPushMessage;
use App\Message\SendSmsMessage;
use App\Message\UnsupportedNotificationMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class JsonMessageSerializer implements SerializerInterface
{
    /**
     * @param  array{body: string, headers?: array<string, string>}  $encodedEnvelope
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        $body = $encodedEnvelope['body'] ?? '';
        $data = json_decode($body, true);

        if (! is_array($data)) {
            throw new MessageDecodingFailedException('El mensaje no es JSON válido.');
        }

        $eventType = $data['event_type']
            ?? ($encodedEnvelope['headers']['type'] ?? null);

        try {
            $message = match ($eventType) {
                'email.send.requested' => SendEmailMessage::fromArray($data),
                'push.send.requested' => SendPushMessage::fromArray($data),
                'sms.send.requested' => SendSmsMessage::fromArray($data),
                default => new UnsupportedNotificationMessage(
                    $data,
                    filled($eventType)
                        ? "Tipo de evento no soportado: {$eventType}."
                        : 'event_type es obligatorio.',
                ),
            };
        } catch (PermanentNotificationException $exception) {
            $message = new UnsupportedNotificationMessage($data, $exception->getMessage());
        }

        return new Envelope($message);
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        if (! method_exists($message, 'toArray')) {
            throw new MessageDecodingFailedException('El mensaje no se puede serializar a JSON.');
        }

        $headers = [
            'Content-Type' => 'application/json',
            'type' => $message->eventType,
        ];

        return [
            'body' => json_encode($message->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'headers' => $headers,
        ];
    }
}
