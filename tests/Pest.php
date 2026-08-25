<?php

use App\Channels\Email\RenderedEmail;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

/**
 * @param  array<string, mixed>  $overrides
 */
function makeRenderedEmail(array $overrides = []): RenderedEmail
{
    return new RenderedEmail(
        to: $overrides['to'] ?? [['email' => 'user@example.com', 'name' => 'Usuario']],
        cc: $overrides['cc'] ?? [],
        bcc: $overrides['bcc'] ?? [],
        replyTo: $overrides['replyTo'] ?? null,
        from: $overrides['from'] ?? ['address' => 'noreply@example.com', 'name' => 'App'],
        subject: $overrides['subject'] ?? 'Hola',
        html: array_key_exists('html', $overrides) ? $overrides['html'] : '<p>Cuerpo</p>',
        text: array_key_exists('text', $overrides) ? $overrides['text'] : 'Cuerpo',
    );
}
