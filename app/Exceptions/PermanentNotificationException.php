<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

class PermanentNotificationException extends RuntimeException implements UnrecoverableExceptionInterface {}
