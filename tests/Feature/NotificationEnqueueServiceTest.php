<?php

use App\Enums\InboxStatus;
use App\Enums\NotificationChannel;
use App\Message\SendEmailMessage;
use App\Repositories\InboxEventRepository;
use App\Services\NotificationEnqueueService;
use Tests\Concerns\InteractsWithMongoInbox;

uses(InteractsWithMongoInbox::class);

beforeEach(function () {
    $this->setUpMongoInbox();
    $this->inbox = $this->app->make(InboxEventRepository::class);
    $this->published = fakeMessengerSend();
});

it('persists and publishes a new email event', function () {
    $message = SendEmailMessage::fromArray([
        'event_id' => 'enq-1',
        'idempotency_key' => 'key-1',
        'payload' => enqueuePayload(),
    ]);

    $event = app(NotificationEnqueueService::class)->enqueue($message);

    expect($event->status)->toBe(InboxStatus::Received);
    expect($event->event_id)->toBe('enq-1');
    expect($this->published->messages)->toHaveCount(1);
    expect($this->published->messages[0]->eventId)->toBe('enq-1');
    expect($this->published->messages[0]->idempotencyKey)->toBe('key-1');
});

it('does not publish a duplicate that is already sent', function () {
    $message = SendEmailMessage::fromArray([
        'event_id' => 'enq-sent',
        'payload' => enqueuePayload(),
    ]);

    $persist = $this->inbox->persistNew(
        NotificationChannel::Email,
        'enq-sent',
        NotificationChannel::Email->eventType(),
        null,
        null,
        enqueuePayload(),
    );
    $this->inbox->markSent($persist->event, 'log');

    $event = app(NotificationEnqueueService::class)->enqueue($message);

    expect($event->status)->toBe(InboxStatus::Sent);
    expect($this->published->messages)->toHaveCount(0);
});

it('does not publish a duplicate permanent failure', function () {
    $message = SendEmailMessage::fromArray([
        'event_id' => 'enq-perm',
        'payload' => enqueuePayload(),
    ]);

    $persist = $this->inbox->persistNew(
        NotificationChannel::Email,
        'enq-perm',
        NotificationChannel::Email->eventType(),
        null,
        null,
        enqueuePayload(),
    );
    $this->inbox->markFailed($persist->event, 'plantilla inválida', false);

    $event = app(NotificationEnqueueService::class)->enqueue($message);

    expect($event->status)->toBe(InboxStatus::Failed);
    expect($event->retryable)->toBeFalse();
    expect($this->published->messages)->toHaveCount(0);
});

it('republishes a duplicate that is still received', function () {
    $message = SendEmailMessage::fromArray([
        'event_id' => 'enq-dup',
        'idempotency_key' => 'shared',
        'payload' => enqueuePayload(),
    ]);

    app(NotificationEnqueueService::class)->enqueue($message);
    expect($this->published->messages)->toHaveCount(1);

    $again = app(NotificationEnqueueService::class)->enqueue($message);

    expect($again->status)->toBe(InboxStatus::Received);
    expect($this->published->messages)->toHaveCount(2);
});

it('publishes using the stored event id when the idempotency key matches', function () {
    $first = SendEmailMessage::fromArray([
        'event_id' => 'enq-original',
        'idempotency_key' => 'same-key',
        'payload' => enqueuePayload(),
    ]);
    $second = SendEmailMessage::fromArray([
        'event_id' => 'enq-other',
        'idempotency_key' => 'same-key',
        'payload' => enqueuePayload(),
    ]);

    app(NotificationEnqueueService::class)->enqueue($first);
    app(NotificationEnqueueService::class)->enqueue($second);

    expect($this->published->messages)->toHaveCount(2);
    expect($this->published->messages[1]->eventId)->toBe('enq-original');
    expect($this->inbox->findByEventId('enq-other'))->toBeNull();
});

/**
 * @return array<string, mixed>
 */
function enqueuePayload(): array
{
    return [
        'to' => [['email' => 'user@example.com']],
        'content' => ['subject' => 'Hola', 'text' => 'Cuerpo'],
    ];
}
