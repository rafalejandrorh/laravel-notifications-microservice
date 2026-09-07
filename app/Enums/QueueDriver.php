<?php

namespace App\Enums;

use InvalidArgumentException;

enum QueueDriver: string
{
    case RabbitMq = 'rabbitmq';
    case Laravel = 'laravel';

    public static function current(): self
    {
        $value = (string) config('notifications.queue_driver', self::RabbitMq->value);

        return self::tryFrom($value)
            ?? throw new InvalidArgumentException(
                "NOTIFICATION_QUEUE_DRIVER [{$value}] no es válido. Use rabbitmq o laravel.",
            );
    }

    public function isRabbitMq(): bool
    {
        return $this === self::RabbitMq;
    }

    public function isLaravel(): bool
    {
        return $this === self::Laravel;
    }
}
