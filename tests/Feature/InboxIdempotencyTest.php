<?php

use App\Enums\InboxStatus;
use App\Enums\NotificationChannel;
use App\Message\SendEmailMessage;
use App\Models\InboxEvent;
use App\Repositories\InboxEventRepository;
use App\Services\NotificationDispatchService;
use Tests\Concerns\InteractsWithMongoInbox;

uses(InteractsWithMongoInbox::class);

beforeEach(function () {
    $this->setUpMongoInbox();
    $this->inbox = $this->app->make(InboxEventRepository::class);
});

it('does not send twice for a duplicate event id', function () {
    $payload = rawEmailPayload();
    $first = dispatchEmail('evt-1', 'key-a', $payload);
    $second = dispatchEmail('evt-1', 'key-b', $payload);

    expect($first->status)->toBe(InboxStatus::Sent);
    expect($second->event_id)->toBe($first->event_id);
    expect($this->inbox->findByEventId('evt-1')?->attempts)->toBe(1);
});

it('does not send twice for a duplicate idempotency key on the same channel', function () {
    $payload = rawEmailPayload();
    $first = dispatchEmail('evt-10', 'shared-key', $payload);
    $second = dispatchEmail('evt-11', 'shared-key', $payload);

    expect($first->status)->toBe(InboxStatus::Sent);
    expect($second->event_id)->toBe($first->event_id);
    expect($this->inbox->findByEventId('evt-11'))->toBeNull();
});

it('allows the same idempotency key on another channel', function () {
    $email = $this->inbox->persistNew(
        NotificationChannel::Email,
        'evt-email',
        NotificationChannel::Email->eventType(),
        null,
        'shared-across-channels',
        rawEmailPayload(),
    );
    $sms = $this->inbox->persistNew(
        NotificationChannel::Sms,
        'evt-sms',
        NotificationChannel::Sms->eventType(),
        null,
        'shared-across-channels',
        ['to' => ['+580000000000'], 'content' => ['text' => 'hola']],
    );

    expect($email->wasInserted)->toBeTrue();
    expect($sms->wasInserted)->toBeTrue();
    expect($email->event->channel)->toBe(NotificationChannel::Email);
    expect($sms->event->channel)->toBe(NotificationChannel::Sms);
});

/**
 * @param  array<string, mixed>  $payload
 */
function dispatchEmail(string $eventId, string $idempotencyKey, array $payload): InboxEvent
{
    return app(NotificationDispatchService::class)->dispatch(
        SendEmailMessage::fromArray([
            'event_id' => $eventId,
            'event_type' => 'email.send.requested',
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
        ]),
    );
}

/**
 * @return array<string, mixed>
 */
function rawEmailPayload(): array
{
    return [
        'to' => [['email' => 'user@example.com', 'name' => 'Usuario']],
        'content' => [
            'subject' => 'Hola',
            'text' => 'Cuerpo',
        ],
    ];
}
