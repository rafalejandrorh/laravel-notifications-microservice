<?php

namespace App\Message;

use App\Enums\NotificationChannel;

final readonly class SendSmsMessage extends NotificationMessage
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::Sms;
    }

    public static function defaultEventType(): string
    {
        return NotificationChannel::Sms->eventType();
    }
}
