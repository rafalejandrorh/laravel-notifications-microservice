<?php

use App\Enums\NotificationChannel;
use App\Exceptions\PermanentNotificationException;
use App\Message\SendEmailMessage;
use App\Message\SendPushMessage;
use App\Message\SendSmsMessage;
use App\Models\InboxEvent;

it('exposes channel and default event type for push and sms', function () {
    $push = SendPushMessage::fromArray(['event_id' => '550e8400-e29b-41d4-a716-446655440000']);
    $sms = SendSmsMessage::fromArray(['event_id' => '550e8400-e29b-41d4-a716-446655440001']);

    expect($push->channel())->toBe(NotificationChannel::Push);
    expect(SendPushMessage::defaultEventType())->toBe('push.send.requested');
    expect($sms->channel())->toBe(NotificationChannel::Sms);
    expect(SendSmsMessage::defaultEventType())->toBe('sms.send.requested');
});

it('rebuilds a message from an inbox event', function () {
    $event = new InboxEvent([
        'event_id' => 'evt-1',
        'event_type' => 'email.send.requested',
        'occurred_at' => '2026-01-15T12:00:00+00:00',
        'idempotency_key' => 'key-1',
        'payload' => ['to' => [['email' => 'user@example.com']]],
    ]);

    $message = SendEmailMessage::fromInbox($event);

    expect($message->eventId)->toBe('evt-1');
    expect($message->eventType)->toBe('email.send.requested');
    expect($message->occurredAt)->toBe($event->occurred_at->toIso8601String());
    expect($message->idempotencyKey)->toBe('key-1');
    expect($message->payload)->toBe(['to' => [['email' => 'user@example.com']]]);
});

it('defaults payload and occurred at when the inbox event omits them', function () {
    $message = SendEmailMessage::fromInbox(new InboxEvent([
        'event_id' => 'evt-2',
        'event_type' => 'email.send.requested',
    ]));

    expect($message->occurredAt)->toBeNull();
    expect($message->idempotencyKey)->toBeNull();
    expect($message->payload)->toBe([]);
    expect($message->toArray())->not->toHaveKey('idempotency_key');
});

it('requires an event id', function () {
    SendEmailMessage::fromArray(['payload' => []]);
})->throws(PermanentNotificationException::class, 'event_id es obligatorio.');
