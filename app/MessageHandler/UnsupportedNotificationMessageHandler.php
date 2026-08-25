<?php

namespace App\MessageHandler;

use App\Exceptions\PermanentNotificationException;
use App\Message\UnsupportedNotificationMessage;

class UnsupportedNotificationMessageHandler
{
    public function __invoke(UnsupportedNotificationMessage $message): void
    {
        throw new PermanentNotificationException($message->reason);
    }
}
