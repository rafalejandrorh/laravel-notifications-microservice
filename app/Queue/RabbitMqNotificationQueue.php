<?php

namespace App\Queue;

use App\Contracts\NotificationQueue;
use App\Message\NotificationMessage;
use App\Messenger\MessengerFactory;

class RabbitMqNotificationQueue implements NotificationQueue
{
    public function __construct(private MessengerFactory $messenger) {}

    public function publish(NotificationMessage $message): void
    {
        $this->messenger->send($message);
    }
}
