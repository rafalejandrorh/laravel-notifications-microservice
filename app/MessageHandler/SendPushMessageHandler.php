<?php

namespace App\MessageHandler;

use App\Message\SendPushMessage;
use App\Services\NotificationDispatchService;

class SendPushMessageHandler
{
    public function __construct(
        private NotificationDispatchService $dispatcher,
    ) {}

    public function __invoke(SendPushMessage $message): void
    {
        $this->dispatcher->dispatch($message);
    }
}
