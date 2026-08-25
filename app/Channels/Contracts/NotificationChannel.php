<?php

namespace App\Channels\Contracts;

use App\Channels\ProviderResult;
use App\Channels\RenderedNotification;
use App\Models\InboxEvent;

interface NotificationChannel
{
    public function render(InboxEvent $event): RenderedNotification;

    public function send(InboxEvent $event): ProviderResult;

    public function supported(): bool;
}
