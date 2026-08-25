<?php

namespace App\Message;

final readonly class UnsupportedNotificationMessage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public string $reason,
    ) {}
}
