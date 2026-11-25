<?php

namespace App\Messenger;

use App\Exceptions\TransientNotificationException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;

class RecoverableNotificationException extends TransientNotificationException implements RecoverableExceptionInterface
{
    public function getRetryDelay(): ?int
    {
        return null;
    }
}
