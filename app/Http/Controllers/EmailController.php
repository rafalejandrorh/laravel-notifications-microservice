<?php

namespace App\Http\Controllers;

use App\Enums\InboxStatus;
use App\Enums\NotificationChannel;
use App\Http\Requests\StoreEmailRequest;
use App\Message\SendEmailMessage;
use App\Models\InboxEvent;
use App\Repositories\InboxEventRepository;
use App\Services\NotificationDispatchService;
use Illuminate\Http\JsonResponse;

class EmailController extends Controller
{
    public function __construct(
        private NotificationDispatchService $dispatcher,
        private InboxEventRepository $inbox,
    ) {}

    public function store(StoreEmailRequest $request): JsonResponse
    {
        $event = $this->dispatcher->dispatch($request->toMessage());

        return response()->json($this->present($event), 202);
    }

    public function retry(string $eventId): JsonResponse
    {
        $event = $this->inbox->findByEventId($eventId);

        if ($event === null) {
            return response()->json(['message' => 'Evento no encontrado.'], 404);
        }

        if ($event->channel !== NotificationChannel::Email) {
            return response()->json(['message' => 'El evento no pertenece al canal email.'], 422);
        }

        if ($event->status === InboxStatus::Sent) {
            return response()->json(['message' => 'El evento ya fue enviado.'], 409);
        }

        $event = $this->inbox->prepareManualRetry($event);
        $event = $this->dispatcher->dispatch(SendEmailMessage::fromInbox($event));

        return response()->json($this->present($event), 202);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(InboxEvent $event): array
    {
        return [
            'event_id' => $event->event_id,
            'channel' => $event->channel?->value,
            'status' => $event->status?->value,
            'attempts' => $event->attempts,
            'retryable' => $event->retryable,
            'resolved_provider' => $event->resolved_provider,
            'resolved_template' => $event->resolved_template,
            'resolved_version' => $event->resolved_version,
            'last_error' => $event->last_error,
        ];
    }
}
