<?php

namespace App\Services;

use App\Contracts\NotificationQueue;
use App\Enums\InboxStatus;
use App\Message\NotificationMessage;
use App\Models\InboxEvent;
use App\Repositories\InboxEventRepository;

class NotificationEnqueueService
{
    public function __construct(
        private InboxEventRepository $inbox,
        private NotificationQueue $queue,
    ) {}

    public function enqueue(NotificationMessage $message): InboxEvent
    {
        $persist = $this->inbox->persistNew(
            $message->channel(),
            $message->eventId,
            $message->eventType,
            $message->occurredAt,
            $message->idempotencyKey,
            $message->payload,
        );

        $event = $persist->event;

        if ($this->shouldSkipPublish($event)) {
            return $event;
        }

        $this->queue->publish($message::fromInbox($event));

        return $event;
    }

    private function shouldSkipPublish(InboxEvent $event): bool
    {
        if ($event->status?->isTerminal()) {
            return true;
        }

        return $event->status === InboxStatus::Failed && ! $event->retryable;
    }
}
