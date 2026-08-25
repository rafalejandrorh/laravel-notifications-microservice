<?php

namespace App\Services;

use App\Channels\ChannelRegistry;
use App\Enums\InboxStatus;
use App\Exceptions\PermanentNotificationException;
use App\Message\NotificationMessage;
use App\Models\InboxEvent;
use App\Repositories\InboxEventRepository;
use Throwable;

class NotificationDispatchService
{
    public function __construct(
        private InboxEventRepository $inbox,
        private ChannelRegistry $channels,
    ) {}

    public function dispatch(NotificationMessage $message): InboxEvent
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

        if ($event->status?->isTerminal()) {
            return $event;
        }

        if ($persist->wasDuplicate() && $event->status === InboxStatus::Failed && ! $event->retryable) {
            return $event;
        }

        $claimed = $this->inbox->claim($event->event_id, $this->workerId());

        if ($claimed === null) {
            return $this->inbox->findByEventId($event->event_id) ?? $event;
        }

        $maxAttempts = (int) config('notifications.max_send_attempts', 5);

        if ($claimed->attempts > $maxAttempts) {
            return $this->inbox->markFailed($claimed, 'Se agotaron los intentos de envío.', false);
        }

        try {
            $channel = $this->channels->for($message->channel());

            if (! $channel->supported()) {
                return $this->inbox->markFailed(
                    $claimed,
                    "Canal [{$message->channel()->value}] no habilitado.",
                    false,
                );
            }

            if (! $claimed->hasResolvedContent()) {
                $rendered = $channel->render($claimed);
                $claimed = $this->inbox->storeRendered(
                    $claimed,
                    $rendered->content,
                    $rendered->templateName,
                    $rendered->templateVersion,
                    $rendered->fromIdentity,
                    $rendered->from,
                );
            }

            $result = $channel->send($claimed);

            return $this->inbox->markSent($claimed, $result->provider, $result->messageId);
        } catch (PermanentNotificationException $exception) {
            return $this->inbox->markFailed($claimed, $exception->getMessage(), false);
        } catch (Throwable $exception) {
            $this->inbox->markFailed($claimed, $exception->getMessage(), true);

            throw $exception;
        }
    }

    private function workerId(): string
    {
        return sprintf('%s:%d', gethostname() ?: 'worker', getmypid() ?: 0);
    }
}
