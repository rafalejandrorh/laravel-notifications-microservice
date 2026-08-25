<?php

use App\Message\SendEmailMessage;
use App\Message\SendPushMessage;
use App\Message\UnsupportedNotificationMessage;
use App\Messenger\JsonMessageSerializer;
use Symfony\Component\Messenger\Envelope;

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
