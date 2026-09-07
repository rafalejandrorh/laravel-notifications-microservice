<?php

namespace App\Jobs;

use App\Message\NotificationMessage;
use App\Repositories\InboxEventRepository;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $eventId) {}

    public function tries(): int
    {
        return (int) config('notifications.max_send_attempts', 5);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $retry = config('notifications.queue.retry', []);
        $delay = (int) ($retry['delay_seconds'] ?? 1);
        $multiplier = (float) ($retry['multiplier'] ?? 2);
        $maxDelay = (int) ($retry['max_delay_seconds'] ?? 60);
        $steps = max(1, $this->tries() - 1);
        $backoff = [];
        $current = $delay;

        for ($i = 0; $i < $steps; $i++) {
            $backoff[] = min($maxDelay, (int) round($current));
            $current *= $multiplier;
        }

        return $backoff;
    }

    public function handle(InboxEventRepository $inbox, NotificationDispatchService $dispatcher): void
    {
        $event = $inbox->findByEventId($this->eventId);

        if ($event === null) {
            return;
        }

        $dispatcher->dispatch(NotificationMessage::fromInboxEvent($event));
    }
}
