<?php

namespace App\Contracts;

use App\Message\NotificationMessage;

interface NotificationQueue
{
    public function publish(NotificationMessage $message): void;
}
