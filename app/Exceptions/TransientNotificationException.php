<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;

class TransientNotificationException extends RuntimeException implements RecoverableExceptionInterface
{
    public function getRetryDelay(): ?int
    {
        return null;
    }
}
