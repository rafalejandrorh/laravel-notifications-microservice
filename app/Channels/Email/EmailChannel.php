<?php

namespace App\Channels\Email;

use App\Channels\Contracts\NotificationChannel as NotificationChannelContract;
use App\Channels\ProviderResult;
use App\Channels\RenderedNotification;
use App\Models\InboxEvent;

class EmailChannel implements NotificationChannelContract
{
    public function __construct(
        private EmailContentResolver $contentResolver,
        private MailProviderResolver $mailProviders,
    ) {}

    public function supported(): bool
    {
        return true;
    }

    public function render(InboxEvent $event): RenderedNotification
    {
        return $this->contentResolver->resolve($event);
    }

    public function send(InboxEvent $event): ProviderResult
    {
        $rendered = $event->rendered ?? [];
        $payload = $event->payload ?? [];
        $from = $event->resolved_from ?? $this->contentResolver->fromIdentity('noreply');

        $message = new RenderedEmail(
            to: EmailContentResolver::normalizeAddresses($payload['to'] ?? []),
            cc: EmailContentResolver::normalizeAddresses($payload['cc'] ?? []),
            bcc: EmailContentResolver::normalizeAddresses($payload['bcc'] ?? []),
            replyTo: EmailContentResolver::normalizeAddress($payload['reply_to'] ?? null),
            from: [
                'address' => (string) ($from['address'] ?? config('mail.from.address')),
                'name' => $from['name'] ?? config('mail.from.name'),
            ],
            subject: (string) ($rendered['subject'] ?? ''),
            html: isset($rendered['html']) ? (string) $rendered['html'] : null,
            text: isset($rendered['text']) ? (string) $rendered['text'] : null,
        );

        return $this->mailProviders->resolve()->send($message);
    }
}
