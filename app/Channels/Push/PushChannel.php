<?php

namespace App\Channels\Push;

use App\Channels\Contracts\NotificationChannel as NotificationChannelContract;
use App\Channels\ProviderResult;
use App\Channels\RenderedNotification;
use App\Exceptions\ChannelNotEnabledException;
use App\Models\InboxEvent;

class PushChannel implements NotificationChannelContract
{
    public function supported(): bool
    {
        return false;
    }

    public function render(InboxEvent $event): RenderedNotification
    {
        throw ChannelNotEnabledException::for('push');
    }

    public function send(InboxEvent $event): ProviderResult
    {
        throw ChannelNotEnabledException::for('push');
    }
}
