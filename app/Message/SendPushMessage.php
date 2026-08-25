<?php

namespace App\Message;

use App\Enums\NotificationChannel;

final readonly class SendPushMessage extends NotificationMessage
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::Push;
    }

    public static function defaultEventType(): string
    {
        return NotificationChannel::Push->eventType();
    }
}
