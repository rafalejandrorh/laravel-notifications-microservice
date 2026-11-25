<?php

use App\Enums\QueueDriver;

it('reads the configured queue driver', function () {
    config(['notifications.queue_driver' => 'laravel']);

    expect(QueueDriver::current())->toBe(QueueDriver::Laravel)
        ->and(QueueDriver::current()->isLaravel())->toBeTrue()
        ->and(QueueDriver::current()->isRabbitMq())->toBeFalse();
});

it('defaults to rabbitmq', function () {
    config(['notifications.queue_driver' => 'rabbitmq']);

    expect(QueueDriver::current())->toBe(QueueDriver::RabbitMq)
        ->and(QueueDriver::current()->isRabbitMq())->toBeTrue();
});

it('rejects an unknown queue driver', function () {
    config(['notifications.queue_driver' => 'sqs']);

    QueueDriver::current();
})->throws(InvalidArgumentException::class, 'NOTIFICATION_QUEUE_DRIVER [sqs] no es válido. Use rabbitmq o laravel.');
