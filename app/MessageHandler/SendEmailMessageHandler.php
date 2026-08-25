<?php

namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use App\Services\NotificationDispatchService;

class SendEmailMessageHandler
{
    public function __construct(
        private NotificationDispatchService $dispatcher,
    ) {}

    public function __invoke(SendEmailMessage $message): void
    {
        $this->dispatcher->dispatch($message);
    }
}
