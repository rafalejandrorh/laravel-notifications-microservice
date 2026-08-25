<?php

namespace App\Message;

use App\Enums\NotificationChannel;
use App\Exceptions\PermanentNotificationException;
use App\Models\InboxEvent;

abstract readonly class NotificationMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public ?string $occurredAt,
        public ?string $idempotencyKey,
        public array $payload,
    ) {}

    abstract public function channel(): NotificationChannel;

    abstract public static function defaultEventType(): string;

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $eventId = trim((string) ($data['event_id'] ?? ''));

        if ($eventId === '') {
            throw new PermanentNotificationException('event_id es obligatorio.');
        }

        $idempotencyKey = $data['idempotency_key'] ?? null;

        return new static(
            eventId: $eventId,
            eventType: (string) ($data['event_type'] ?? static::defaultEventType()),
            occurredAt: isset($data['occurred_at']) ? (string) $data['occurred_at'] : null,
            idempotencyKey: filled($idempotencyKey) ? (string) $idempotencyKey : null,
            payload: is_array($data['payload'] ?? null) ? $data['payload'] : [],
        );
    }

    public static function fromInbox(InboxEvent $event): static
    {
        return new static(
            eventId: $event->event_id,
            eventType: $event->event_type,
            occurredAt: $event->occurred_at?->toIso8601String(),
            idempotencyKey: $event->idempotency_key,
            payload: $event->payload ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'occurred_at' => $this->occurredAt,
            'payload' => $this->payload,
        ];

        if (filled($this->idempotencyKey)) {
            $data['idempotency_key'] = $this->idempotencyKey;
        }

        return $data;
    }
}
