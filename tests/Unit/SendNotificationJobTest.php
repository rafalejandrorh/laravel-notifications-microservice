<?php

use App\Enums\NotificationChannel;
use App\Jobs\SendNotificationJob;
use App\Message\SendEmailMessage;
use App\Models\InboxEvent;
use App\Repositories\InboxEventRepository;
use App\Services\NotificationDispatchService;

it('loads the inbox event and dispatches it', function () {
    $event = new InboxEvent([
        'event_id' => 'job-1',
        'event_type' => NotificationChannel::Email->eventType(),
        'channel' => NotificationChannel::Email,
        'payload' => ['to' => [['email' => 'user@example.com']]],
    ]);

    $inbox = Mockery::mock(InboxEventRepository::class);
    $inbox->shouldReceive('findByEventId')->once()->with('job-1')->andReturn($event);

    $dispatcher = Mockery::mock(NotificationDispatchService::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(fn (SendEmailMessage $message): bool => $message->eventId === 'job-1'))
        ->andReturn($event);

    (new SendNotificationJob('job-1'))->handle($inbox, $dispatcher);
});

it('does nothing when the inbox event is missing', function () {
    $inbox = Mockery::mock(InboxEventRepository::class);
    $inbox->shouldReceive('findByEventId')->once()->with('missing')->andReturn(null);

    $dispatcher = Mockery::mock(NotificationDispatchService::class);
    $dispatcher->shouldReceive('dispatch')->never();

    (new SendNotificationJob('missing'))->handle($inbox, $dispatcher);
});

it('aligns tries and backoff with notification queue retry config', function () {
    config([
        'notifications.max_send_attempts' => 4,
        'notifications.queue.retry.delay_seconds' => 1,
        'notifications.queue.retry.multiplier' => 2,
        'notifications.queue.retry.max_delay_seconds' => 60,
    ]);

    $job = new SendNotificationJob('job-retry');

    expect($job->tries())->toBe(4)
        ->and($job->backoff())->toBe([1, 2, 4]);
});
