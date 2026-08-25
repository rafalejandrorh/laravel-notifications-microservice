<?php

namespace App\Channels;

use App\Channels\Contracts\NotificationChannel as NotificationChannelContract;
use App\Enums\NotificationChannel;
use InvalidArgumentException;

class ChannelRegistry
{
    /**
     * @param  array<string, NotificationChannelContract>  $channels
     */
    public function __construct(
        private array $channels,
    ) {}

    public function for(NotificationChannel $channel): NotificationChannelContract
    {
        $driver = $this->channels[$channel->value] ?? null;

        if ($driver === null) {
            throw new InvalidArgumentException("No hay implementación para el canal [{$channel->value}].");
        }

        return $driver;
    }
}
