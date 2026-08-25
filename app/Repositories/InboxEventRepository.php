<?php

namespace App\Repositories;

use App\Enums\InboxStatus;
use App\Enums\NotificationChannel;
use App\Models\InboxEvent;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Laravel\Connection;
use MongoDB\Operation\FindOneAndUpdate;
use Throwable;

class InboxEventRepository
{
    public function persistNew(
        NotificationChannel $channel,
        string $eventId,
        string $eventType,
        ?string $occurredAt,
        ?string $idempotencyKey,
        array $payload,
    ): InboxPersistResult {
        $attributes = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'channel' => $channel,
            'occurred_at' => $occurredAt,
            'payload' => $payload,
            'status' => InboxStatus::Received,
            'attempts' => 0,
            'retryable' => true,
        ];

        if (filled($idempotencyKey)) {
            $attributes['idempotency_key'] = $idempotencyKey;
        }

        try {
            $event = InboxEvent::query()->create($attributes);

            return new InboxPersistResult($event, true);
        } catch (Throwable $exception) {
            if (! $this->isDuplicateKey($exception)) {
                throw $exception;
            }
        }

        $existing = $this->findByEventId($eventId)
            ?? (filled($idempotencyKey) ? $this->findByIdempotencyKey($channel, $idempotencyKey) : null);

        if ($existing === null) {
            throw new \RuntimeException("Duplicate inbox write for event [{$eventId}] but no existing document was found.");
        }

        return new InboxPersistResult($existing, false);
    }

    public function findByEventId(string $eventId): ?InboxEvent
    {
        return InboxEvent::query()->where('event_id', $eventId)->first();
    }

    public function findByIdempotencyKey(NotificationChannel $channel, string $idempotencyKey): ?InboxEvent
    {
        return InboxEvent::query()
            ->where('channel', $channel)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function claim(string $eventId, string $workerId, ?int $ttlSeconds = null): ?InboxEvent
    {
        $ttlSeconds ??= (int) config('notifications.claim_ttl_seconds', 300);
        $staleBefore = new UTCDateTime(now()->subSeconds($ttlSeconds));

        $updated = $this->collection()->findOneAndUpdate(
            [
                'event_id' => $eventId,
                '$or' => [
                    ['status' => InboxStatus::Received->value],
                    [
                        'status' => InboxStatus::Failed->value,
                        'retryable' => true,
                    ],
                    [
                        'status' => InboxStatus::Processing->value,
                        'claimed_at' => ['$lt' => $staleBefore],
                    ],
                ],
            ],
            [
                '$set' => [
                    'status' => InboxStatus::Processing->value,
                    'worker_id' => $workerId,
                    'claimed_at' => new UTCDateTime(now()),
                    'updated_at' => new UTCDateTime(now()),
                ],
                '$inc' => [
                    'attempts' => 1,
                ],
            ],
            [
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ],
        );

        if ($updated === null) {
            return null;
        }

        return $this->findByEventId($eventId);
    }

    /**
     * @param  array<string, mixed>  $rendered
     * @param  array<string, mixed>|null  $from
     */
    public function storeRendered(
        InboxEvent $event,
        array $rendered,
        ?string $templateName = null,
        ?int $templateVersion = null,
        ?string $fromIdentity = null,
        ?array $from = null,
    ): InboxEvent {
        $event->forceFill([
            'rendered' => $rendered,
            'resolved_template' => $templateName,
            'resolved_version' => $templateVersion,
            'resolved_from' => $from,
        ]);

        if ($fromIdentity !== null) {
            $event->setAttribute('from_identity', $fromIdentity);
        }

        $event->save();

        return $event;
    }

    public function markSent(InboxEvent $event, string $provider, ?string $messageId = null): InboxEvent
    {
        $event->forceFill([
            'status' => InboxStatus::Sent,
            'resolved_provider' => $provider,
            'provider_message_id' => $messageId,
            'last_error' => null,
            'retryable' => false,
            'processed_at' => now(),
        ]);
        $event->save();

        return $event;
    }

    public function markFailed(InboxEvent $event, string $error, bool $retryable): InboxEvent
    {
        $event->forceFill([
            'status' => InboxStatus::Failed,
            'last_error' => $error,
            'retryable' => $retryable,
            'processed_at' => now(),
        ]);
        $event->save();

        return $event;
    }

    public function markSkippedDuplicate(InboxEvent $event): InboxEvent
    {
        $event->forceFill([
            'status' => InboxStatus::SkippedDuplicate,
            'retryable' => false,
            'processed_at' => now(),
        ]);
        $event->save();

        return $event;
    }

    public function prepareManualRetry(InboxEvent $event): InboxEvent
    {
        $event->forceFill([
            'status' => InboxStatus::Received,
            'retryable' => true,
            'last_error' => null,
            'worker_id' => null,
            'claimed_at' => null,
        ]);
        $event->save();

        return $event;
    }

    public function ensureIndexes(): void
    {
        $collection = $this->collection();

        $collection->createIndex(
            ['event_id' => 1],
            ['unique' => true, 'name' => 'event_id_unique'],
        );

        try {
            $collection->dropIndex('idempotency_key_unique');
        } catch (Throwable) {
            // Índice legado del inbox solo-email; puede no existir.
        }

        $collection->createIndex(
            ['channel' => 1, 'idempotency_key' => 1],
            ['unique' => true, 'sparse' => true, 'name' => 'channel_idempotency_key_unique'],
        );

        $collection->createIndex(
            ['status' => 1],
            ['name' => 'status_idx'],
        );
    }

    private function collection(): Collection
    {
        /** @var Connection $connection */
        $connection = DB::connection('mongodb');

        return $connection->getCollection((new InboxEvent)->getTable());
    }

    private function isDuplicateKey(Throwable $exception): bool
    {
        if ($exception instanceof BulkWriteException && $exception->getCode() === 11000) {
            return true;
        }

        if (str_contains($exception->getMessage(), 'E11000')) {
            return true;
        }

        $previous = $exception->getPrevious();

        return $previous instanceof Throwable && $this->isDuplicateKey($previous);
    }
}
