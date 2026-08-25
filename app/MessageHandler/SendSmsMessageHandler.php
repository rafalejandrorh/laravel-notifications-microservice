<?php

namespace App\MessageHandler;

use App\Message\SendSmsMessage;
use App\Services\NotificationDispatchService;

class SendSmsMessageHandler
{
    public function __construct(
        private NotificationDispatchService $dispatcher,
    ) {}

    public function __invoke(SendSmsMessage $message): void
    {
        $this->dispatcher->dispatch($message);
    }
}
