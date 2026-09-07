<?php

namespace App\Queue;

use App\Contracts\NotificationQueue;
use App\Jobs\SendNotificationJob;
use App\Message\NotificationMessage;

class LaravelNotificationQueue implements NotificationQueue
{
    public function publish(NotificationMessage $message): void
    {
        SendNotificationJob::dispatch($message->eventId);
    }
}
