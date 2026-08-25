<?php

namespace App\Message;

use App\Enums\NotificationChannel;

final readonly class SendEmailMessage extends NotificationMessage
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::Email;
    }

    public static function defaultEventType(): string
    {
        return NotificationChannel::Email->eventType();
    }
}
