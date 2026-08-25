<?php

namespace App\Channels;

final readonly class ProviderResult
{
    public function __construct(
        public string $provider,
        public ?string $messageId = null,
    ) {}
}
