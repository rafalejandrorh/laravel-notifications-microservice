<?php

namespace App\MessageHandler;

use App\Message\UnsupportedNotificationMessage;
use App\Messenger\UnrecoverableNotificationException;

class UnsupportedNotificationMessageHandler
{
    public function __invoke(UnsupportedNotificationMessage $message): void
    {
        throw new UnrecoverableNotificationException($message->reason);
    }
}
