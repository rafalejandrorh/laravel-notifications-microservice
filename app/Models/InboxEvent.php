<?php

namespace App\Models;

use App\Enums\InboxStatus;
use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use MongoDB\Laravel\Eloquent\Model;

#[Fillable([
    'event_id',
    'event_type',
    'channel',
    'occurred_at',
    'idempotency_key',
    'payload',
    'status',
    'attempts',
    'retryable',
    'worker_id',
    'claimed_at',
    'processed_at',
    'last_error',
    'resolved_provider',
    'resolved_from',
    'from_identity',
    'resolved_template',
    'resolved_version',
    'rendered',
    'provider_message_id',
])]
class InboxEvent extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'inbox_events';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InboxStatus::class,
            'channel' => NotificationChannel::class,
            'payload' => 'array',
            'rendered' => 'array',
            'resolved_from' => 'array',
            'attempts' => 'integer',
            'retryable' => 'boolean',
            'resolved_version' => 'integer',
            'occurred_at' => 'datetime',
            'claimed_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function hasResolvedContent(): bool
    {
        $rendered = $this->rendered ?? [];

        return match ($this->channel) {
            NotificationChannel::Email => filled($rendered['subject'] ?? null)
                && (filled($rendered['html'] ?? null) || filled($rendered['text'] ?? null)),
            NotificationChannel::Push => filled($rendered['title'] ?? null)
                && filled($rendered['body'] ?? null),
            NotificationChannel::Sms => filled($rendered['text'] ?? null),
            default => $rendered !== [],
        };
    }

    public function canBeClaimed(): bool
    {
        if ($this->status === InboxStatus::Received) {
            return true;
        }

        if ($this->status === InboxStatus::Failed && $this->retryable) {
            return true;
        }

        return false;
    }
}
