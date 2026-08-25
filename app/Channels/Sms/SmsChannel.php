<?php

namespace App\Channels\Sms;

use App\Channels\Contracts\NotificationChannel as NotificationChannelContract;
use App\Channels\ProviderResult;
use App\Channels\RenderedNotification;
use App\Exceptions\ChannelNotEnabledException;
use App\Models\InboxEvent;

class SmsChannel implements NotificationChannelContract
{
    public function supported(): bool
    {
        return false;
    }

    public function render(InboxEvent $event): RenderedNotification
    {
        throw ChannelNotEnabledException::for('sms');
    }

    public function send(InboxEvent $event): ProviderResult
    {
        throw ChannelNotEnabledException::for('sms');
    }
}
