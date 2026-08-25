<?php

namespace App\Channels\Email;

use App\Channels\RenderedNotification;
use App\Enums\NotificationChannel;
use App\Exceptions\PermanentNotificationException;
use App\Models\InboxEvent;

class EmailContentResolver
{
    public function __construct(
        private TemplateCatalog $catalog,
        private TemplateRenderer $renderer,
    ) {}

    public function resolve(InboxEvent $event): RenderedNotification
    {
        $payload = $event->payload ?? [];
        $hasTemplate = isset($payload['template']) && is_array($payload['template']);
        $hasContent = isset($payload['content']) && is_array($payload['content']);

        if ($hasTemplate === $hasContent) {
            throw new PermanentNotificationException('Debe enviarse template o content, no ambos ni ninguno.');
        }

        $to = self::normalizeAddresses($payload['to'] ?? []);

        if ($to === []) {
            throw new PermanentNotificationException('payload.to es obligatorio.');
        }

        $this->assertValidEmails([
            ...$to,
            ...self::normalizeAddresses($payload['cc'] ?? []),
            ...self::normalizeAddresses($payload['bcc'] ?? []),
            ...array_filter([self::normalizeAddress($payload['reply_to'] ?? null)]),
        ]);

        if ($hasTemplate) {
            return $this->fromTemplate($payload['template']);
        }

        return $this->fromContent($payload['content']);
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function fromTemplate(array $template): RenderedNotification
    {
        $name = (string) ($template['name'] ?? '');

        if ($name === '') {
            throw new PermanentNotificationException('template.name es obligatorio.');
        }

        $version = isset($template['version']) ? (int) $template['version'] : null;
        $params = is_array($template['params'] ?? null) ? $template['params'] : [];
        $resolved = $this->catalog->resolve(NotificationChannel::Email, $name, $version);
        $rendered = $this->renderer->render($resolved, $params);
        $fromIdentity = $resolved['from_identity'] ?? 'noreply';

        return new RenderedNotification(
            content: $rendered,
            templateName: $resolved['name'],
            templateVersion: $resolved['version'],
            fromIdentity: $fromIdentity,
            from: $this->fromIdentity($fromIdentity),
        );
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function fromContent(array $content): RenderedNotification
    {
        $subject = trim((string) ($content['subject'] ?? ''));
        $html = isset($content['html']) ? (string) $content['html'] : null;
        $text = isset($content['text']) ? (string) $content['text'] : null;

        if ($subject === '') {
            throw new PermanentNotificationException('content.subject es obligatorio.');
        }

        if (! filled($html) && ! filled($text)) {
            throw new PermanentNotificationException('content debe incluir html o text.');
        }

        return new RenderedNotification(
            content: [
                'subject' => $subject,
                'html' => filled($html) ? $html : null,
                'text' => filled($text) ? $text : null,
            ],
            fromIdentity: 'noreply',
            from: $this->fromIdentity('noreply'),
        );
    }

    /**
     * @return array{address: string, name: string|null}
     */
    public function fromIdentity(string $identity): array
    {
        $identities = config('email.from_identities', []);
        $resolved = $identities[$identity] ?? $identities['noreply'] ?? [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ];

        return [
            'address' => (string) ($resolved['address'] ?? config('mail.from.address')),
            'name' => $resolved['name'] ?? config('mail.from.name'),
        ];
    }

    /**
     * @param  list<array{email: string, name: string|null}>  $addresses
     */
    private function assertValidEmails(array $addresses): void
    {
        foreach ($addresses as $address) {
            if (! filter_var($address['email'], FILTER_VALIDATE_EMAIL)) {
                throw new PermanentNotificationException("Dirección de correo inválida: {$address['email']}.");
            }
        }
    }

    /**
     * @return list<array{email: string, name: string|null}>
     */
    public static function normalizeAddresses(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $addresses = [];

        foreach ($value as $item) {
            $normalized = self::normalizeAddress($item);

            if ($normalized !== null) {
                $addresses[] = $normalized;
            }
        }

        return $addresses;
    }

    /**
     * @return array{email: string, name: string|null}|null
     */
    public static function normalizeAddress(mixed $value): ?array
    {
        if (is_string($value) && $value !== '') {
            return ['email' => $value, 'name' => null];
        }

        if (is_array($value) && filled($value['email'] ?? null)) {
            return [
                'email' => (string) $value['email'],
                'name' => isset($value['name']) ? (string) $value['name'] : null,
            ];
        }

        return null;
    }
}
