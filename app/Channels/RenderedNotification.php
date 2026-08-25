<?php

namespace App\Channels;

final readonly class RenderedNotification
{
    /**
     * @param  array<string, mixed>  $content
     * @param  array{address: string, name?: string|null}|null  $from
     */
    public function __construct(
        public array $content,
        public ?string $templateName = null,
        public ?int $templateVersion = null,
        public ?string $fromIdentity = null,
        public ?array $from = null,
    ) {}
}
