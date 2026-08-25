<?php

namespace App\Http\Controllers;

use App\Repositories\InboxEventRepository;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private InboxEventRepository $inbox,
    ) {}

    public function show(string $eventId): JsonResponse
    {
        $event = $this->inbox->findByEventId($eventId);

        if ($event === null) {
            return response()->json(['message' => 'Evento no encontrado.'], 404);
        }

        return response()->json([
            'event_id' => $event->event_id,
            'channel' => $event->channel?->value,
            'event_type' => $event->event_type,
            'status' => $event->status?->value,
            'attempts' => $event->attempts,
            'retryable' => $event->retryable,
            'resolved_provider' => $event->resolved_provider,
            'resolved_from' => $event->resolved_from,
            'resolved_template' => $event->resolved_template,
            'resolved_version' => $event->resolved_version,
            'last_error' => $event->last_error,
            'processed_at' => $event->processed_at,
        ]);
    }
}
