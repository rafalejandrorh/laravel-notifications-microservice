<?php

use App\Jobs\SendNotificationJob;
use App\Message\SendEmailMessage;
use App\Message\SendPushMessage;
use App\Message\SendSmsMessage;
use App\Messenger\MessengerFactory;
use App\Queue\LaravelNotificationQueue;
use App\Queue\RabbitMqNotificationQueue;
use Illuminate\Support\Facades\Queue;

it('publishes through messenger when using rabbitmq', function () {
    $message = SendEmailMessage::fromArray([
        'event_id' => '550e8400-e29b-41d4-a716-446655440000',
        'payload' => [],
    ]);

    $messenger = Mockery::mock(MessengerFactory::class);
    $messenger->shouldReceive('send')->once()->with($message);

    (new RabbitMqNotificationQueue($messenger))->publish($message);
});

it('dispatches a laravel job with the event id', function () {
    Queue::fake();

    $message = SendEmailMessage::fromArray([
        'event_id' => '550e8400-e29b-41d4-a716-446655440010',
        'payload' => [],
    ]);

    (new LaravelNotificationQueue)->publish($message);

    Queue::assertPushedOn('email.send', SendNotificationJob::class, fn (SendNotificationJob $job): bool => $job->eventId === '550e8400-e29b-41d4-a716-446655440010');
});

it('routes laravel jobs to the channel queue', function (string $class, string $queue) {
    Queue::fake();

    $message = $class::fromArray([
        'event_id' => '550e8400-e29b-41d4-a716-446655440011',
        'payload' => [],
    ]);

    (new LaravelNotificationQueue)->publish($message);

    Queue::assertPushedOn($queue, SendNotificationJob::class);
})->with([
    [SendEmailMessage::class, 'email.send'],
    [SendPushMessage::class, 'push.send'],
    [SendSmsMessage::class, 'sms.send'],
]);
