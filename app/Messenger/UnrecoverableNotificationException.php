<?php

namespace App\Messenger;

use App\Exceptions\PermanentNotificationException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

class UnrecoverableNotificationException extends PermanentNotificationException implements UnrecoverableExceptionInterface {}
