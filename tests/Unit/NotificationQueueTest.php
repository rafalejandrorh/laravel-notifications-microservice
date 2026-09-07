<?php

use App\Jobs\SendNotificationJob;
use App\Message\SendEmailMessage;
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

    Queue::assertPushed(SendNotificationJob::class, fn (SendNotificationJob $job): bool => $job->eventId === '550e8400-e29b-41d4-a716-446655440010');
});
