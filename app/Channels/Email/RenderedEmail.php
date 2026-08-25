<?php

namespace App\Channels\Email;

final readonly class RenderedEmail
{
    /**
     * @param  list<array{email: string, name: string|null}>  $to
     * @param  list<array{email: string, name: string|null}>  $cc
     * @param  list<array{email: string, name: string|null}>  $bcc
     * @param  array{email: string, name: string|null}|null  $replyTo
     * @param  array{address: string, name: string|null}  $from
     */
    public function __construct(
        public array $to,
        public array $cc,
        public array $bcc,
        public ?array $replyTo,
        public array $from,
        public string $subject,
        public ?string $html,
        public ?string $text,
    ) {}
}
