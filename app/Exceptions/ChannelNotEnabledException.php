<?php

namespace App\Exceptions;

class ChannelNotEnabledException extends PermanentNotificationException
{
    public static function for(string $channel): self
    {
        return new self("Canal [{$channel}] no habilitado.");
    }
}
