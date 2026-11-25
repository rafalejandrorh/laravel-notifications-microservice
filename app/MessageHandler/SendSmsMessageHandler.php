<?php

namespace App\MessageHandler;

use App\Exceptions\TransientNotificationException;
use App\Message\SendSmsMessage;
use App\Messenger\RecoverableNotificationException;
use App\Services\NotificationDispatchService;

class SendSmsMessageHandler
{
    public function __construct(
        private NotificationDispatchService $dispatcher,
    ) {}

    public function __invoke(SendSmsMessage $message): void
    {
        try {
            $this->dispatcher->dispatch($message);
        } catch (TransientNotificationException $exception) {
            throw new RecoverableNotificationException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }
}
