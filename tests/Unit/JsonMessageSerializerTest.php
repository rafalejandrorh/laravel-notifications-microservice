<?php

use App\Message\SendEmailMessage;
use App\Message\SendPushMessage;
use App\Message\SendSmsMessage;
use App\Message\UnsupportedNotificationMessage;
use App\Messenger\JsonMessageSerializer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

beforeEach(function () {
    $this->serializer = new JsonMessageSerializer;
});

it('round trips an email message', function () {
    $message = SendEmailMessage::fromArray([
        'event_id' => '550e8400-e29b-41d4-a716-446655440000',
        'event_type' => 'email.send.requested',
        'idempotency_key' => 'case-123:welcome',
        'payload' => [
            'to' => [['email' => 'user@example.com']],
            'content' => ['subject' => 'Hola', 'text' => 'Cuerpo'],
        ],
    ]);

    $encoded = $this->serializer->encode(new Envelope($message));
    $decoded = $this->serializer->decode($encoded)->getMessage();

    expect($decoded)->toBeInstanceOf(SendEmailMessage::class);
    expect($decoded->eventId)->toBe($message->eventId);
    expect($encoded['headers']['Content-Type'])->toBe('application/json');
    expect($encoded['headers']['type'])->toBe('email.send.requested');
    expect($encoded['body'])->not->toContain('O:');
});

it('maps push event type to push dto', function () {
    $decoded = $this->serializer->decode([
        'body' => json_encode([
            'event_id' => '550e8400-e29b-41d4-a716-446655440000',
            'event_type' => 'push.send.requested',
            'payload' => ['tokens' => ['abc']],
        ]),
    ])->getMessage();

    expect($decoded)->toBeInstanceOf(SendPushMessage::class);
});

it('maps unknown event types to unsupported message', function () {
    $decoded = $this->serializer->decode([
        'body' => json_encode([
            'event_id' => '550e8400-e29b-41d4-a716-446655440000',
            'event_type' => 'fax.send.requested',
            'payload' => [],
        ]),
    ])->getMessage();

    expect($decoded)->toBeInstanceOf(UnsupportedNotificationMessage::class);
    expect($decoded->reason)->toContain('fax.send.requested');
});

it('maps sms event type to sms dto', function () {
    $decoded = $this->serializer->decode([
        'body' => json_encode([
            'event_id' => '550e8400-e29b-41d4-a716-446655440000',
            'event_type' => 'sms.send.requested',
            'payload' => ['to' => ['+580000000000']],
        ]),
    ])->getMessage();

    expect($decoded)->toBeInstanceOf(SendSmsMessage::class);
});

it('rejects invalid json', function () {
    $this->serializer->decode(['body' => '{not-json']);
})->throws(MessageDecodingFailedException::class, 'El mensaje no es JSON válido.');

it('reads event type from headers when the body omits it', function () {
    $decoded = $this->serializer->decode([
        'body' => json_encode([
            'event_id' => '550e8400-e29b-41d4-a716-446655440000',
            'payload' => [],
        ]),
        'headers' => ['type' => 'email.send.requested'],
    ])->getMessage();

    expect($decoded)->toBeInstanceOf(SendEmailMessage::class);
});

it('marks a missing event type as unsupported', function () {
    $decoded = $this->serializer->decode([
        'body' => json_encode([
            'event_id' => '550e8400-e29b-41d4-a716-446655440000',
            'payload' => [],
        ]),
    ])->getMessage();

    expect($decoded)->toBeInstanceOf(UnsupportedNotificationMessage::class);
    expect($decoded->reason)->toBe('event_type es obligatorio.');
});

it('maps invalid payloads to unsupported instead of failing decode', function () {
    $decoded = $this->serializer->decode([
        'body' => json_encode([
            'event_type' => 'email.send.requested',
            'payload' => [],
        ]),
    ])->getMessage();

    expect($decoded)->toBeInstanceOf(UnsupportedNotificationMessage::class);
    expect($decoded->reason)->toBe('event_id es obligatorio.');
});

it('cannot encode a message without toArray', function () {
    $this->serializer->encode(new Envelope(new UnsupportedNotificationMessage([], 'nope')));
})->throws(MessageDecodingFailedException::class, 'El mensaje no se puede serializar a JSON.');
