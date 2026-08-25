<?php

use App\Channels\ChannelRegistry;
use App\Channels\Contracts\NotificationChannel as NotificationChannelContract;
use App\Channels\Push\PushChannel;
use App\Channels\RenderedNotification;
use App\Channels\Sms\SmsChannel;
use App\Enums\InboxStatus;
use App\Enums\NotificationChannel;
use App\Exceptions\TransientNotificationException;
use App\Message\SendEmailMessage;
use App\Message\SendPushMessage;
use App\Message\SendSmsMessage;
use App\Repositories\InboxEventRepository;
use App\Services\NotificationDispatchService;
use Tests\Concerns\InteractsWithMongoInbox;

uses(InteractsWithMongoInbox::class);

beforeEach(function () {
    $this->setUpMongoInbox();
    $this->inbox = $this->app->make(InboxEventRepository::class);
});

it('does not retry a permanent failure on duplicate dispatch', function () {
    $message = SendEmailMessage::fromArray([
        'event_id' => 'perm-fail-1',
        'payload' => [
            'to' => [['email' => 'user@example.com']],
            'template' => ['name' => 'welcome', 'params' => []],
        ],
    ]);

    $first = app(NotificationDispatchService::class)->dispatch($message);
    $second = app(NotificationDispatchService::class)->dispatch($message);

    expect($first->status)->toBe(InboxStatus::Failed);
    expect($first->retryable)->toBeFalse();
    expect($second->event_id)->toBe($first->event_id);
    expect($second->attempts)->toBe($first->attempts);
});

it('fails permanently when the channel is not enabled', function (string $channel) {
    $message = $channel === 'push'
        ? SendPushMessage::fromArray(['event_id' => 'push-1', 'payload' => []])
        : SendSmsMessage::fromArray(['event_id' => 'sms-1', 'payload' => []]);

    $event = app(NotificationDispatchService::class)->dispatch($message);

    expect($event->status)->toBe(InboxStatus::Failed);
    expect($event->retryable)->toBeFalse();
    expect($event->last_error)->toBe("Canal [{$channel}] no habilitado.");
})->with(['push', 'sms']);

it('marks a transient send failure as retryable and rethrows', function () {
    $channel = Mockery::mock(NotificationChannelContract::class);
    $channel->shouldReceive('supported')->andReturn(true);
    $channel->shouldReceive('render')->andReturn(new RenderedNotification(
        content: ['subject' => 'Hola', 'text' => 'Cuerpo'],
        fromIdentity: 'noreply',
        from: ['address' => 'noreply@example.com', 'name' => 'App'],
    ));
    $channel->shouldReceive('send')->once()->andThrow(new TransientNotificationException('smtp down'));

    $this->app->instance(ChannelRegistry::class, new ChannelRegistry([
        NotificationChannel::Email->value => $channel,
        NotificationChannel::Push->value => new PushChannel,
        NotificationChannel::Sms->value => new SmsChannel,
    ]));

    $message = SendEmailMessage::fromArray([
        'event_id' => 'transient-1',
        'payload' => [
            'to' => [['email' => 'user@example.com']],
            'content' => ['subject' => 'Hola', 'text' => 'Cuerpo'],
        ],
    ]);

    expect(fn () => app(NotificationDispatchService::class)->dispatch($message))
        ->toThrow(TransientNotificationException::class, 'smtp down');

    $event = $this->inbox->findByEventId('transient-1');
    expect($event?->status)->toBe(InboxStatus::Failed);
    expect($event?->retryable)->toBeTrue();
    expect($event?->last_error)->toBe('smtp down');
});

it('returns the in-flight event when claim loses the race', function () {
    $payload = [
        'to' => [['email' => 'user@example.com']],
        'content' => ['subject' => 'Hola', 'text' => 'Cuerpo'],
    ];

    $persist = $this->inbox->persistNew(
        NotificationChannel::Email,
        'inflight-1',
        NotificationChannel::Email->eventType(),
        null,
        null,
        $payload,
    );
    $claimed = $this->inbox->claim($persist->event->event_id, 'worker-1');

    $result = app(NotificationDispatchService::class)->dispatch(SendEmailMessage::fromArray([
        'event_id' => 'inflight-1',
        'payload' => $payload,
    ]));

    expect($claimed)->not->toBeNull();
    expect($result->status)->toBe(InboxStatus::Processing);
    expect($result->event_id)->toBe('inflight-1');
});

it('fails permanently when send attempts are exhausted', function () {
    config(['notifications.max_send_attempts' => 0]);

    $event = app(NotificationDispatchService::class)->dispatch(SendEmailMessage::fromArray([
        'event_id' => 'max-attempts-1',
        'payload' => [
            'to' => [['email' => 'user@example.com']],
            'content' => ['subject' => 'Hola', 'text' => 'Cuerpo'],
        ],
    ]));

    expect($event->status)->toBe(InboxStatus::Failed);
    expect($event->retryable)->toBeFalse();
    expect($event->last_error)->toBe('Se agotaron los intentos de envío.');
});

it('marks an event as skipped duplicate', function () {
    $persist = $this->inbox->persistNew(
        NotificationChannel::Email,
        'skip-1',
        NotificationChannel::Email->eventType(),
        null,
        null,
        ['to' => [['email' => 'user@example.com']]],
    );

    $skipped = $this->inbox->markSkippedDuplicate($persist->event);

    expect($skipped->status)->toBe(InboxStatus::SkippedDuplicate);
    expect($skipped->retryable)->toBeFalse();

    $again = app(NotificationDispatchService::class)->dispatch(SendEmailMessage::fromArray([
        'event_id' => 'skip-1',
        'payload' => ['to' => [['email' => 'user@example.com']]],
    ]));

    expect($again->status)->toBe(InboxStatus::SkippedDuplicate);
    expect($again->attempts)->toBe(0);
});
