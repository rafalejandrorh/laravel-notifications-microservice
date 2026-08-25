<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Push = 'push';
    case Sms = 'sms';

    public function eventType(): string
    {
        return match ($this) {
            self::Email => 'email.send.requested',
            self::Push => 'push.send.requested',
            self::Sms => 'sms.send.requested',
        };
    }

    public function routingKey(): string
    {
        return match ($this) {
            self::Email => 'email.send',
            self::Push => 'push.send',
            self::Sms => 'sms.send',
        };
    }
}
