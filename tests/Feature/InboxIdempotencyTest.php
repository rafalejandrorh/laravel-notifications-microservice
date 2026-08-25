<?php

namespace Tests\Feature;

use App\Enums\InboxStatus;
use App\Enums\NotificationChannel;
use App\Message\SendEmailMessage;
use App\Models\InboxEvent;
use App\Repositories\InboxEventRepository;
use App\Services\NotificationDispatchService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithMongoInbox;
use Tests\TestCase;

class InboxIdempotencyTest extends TestCase
{
    use InteractsWithMongoInbox;

    private InboxEventRepository $inbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMongoInbox();
        $this->inbox = $this->app->make(InboxEventRepository::class);
    }

    #[Test]
    public function duplicate_event_id_does_not_send_twice(): void
    {
        $payload = $this->rawPayload();
        $first = $this->dispatch('evt-1', 'key-a', $payload);
        $second = $this->dispatch('evt-1', 'key-b', $payload);

        $this->assertSame(InboxStatus::Sent, $first->status);
        $this->assertSame($first->event_id, $second->event_id);
        $this->assertSame(1, $this->inbox->findByEventId('evt-1')?->attempts);
    }

    #[Test]
    public function duplicate_idempotency_key_on_the_same_channel_does_not_send_twice(): void
    {
        $payload = $this->rawPayload();
        $first = $this->dispatch('evt-10', 'shared-key', $payload);
        $second = $this->dispatch('evt-11', 'shared-key', $payload);

        $this->assertSame(InboxStatus::Sent, $first->status);
        $this->assertSame($first->event_id, $second->event_id);
        $this->assertNull($this->inbox->findByEventId('evt-11'));
    }

    #[Test]
    public function the_same_idempotency_key_can_be_used_on_another_channel(): void
    {
        $email = $this->inbox->persistNew(
            NotificationChannel::Email,
            'evt-email',
            NotificationChannel::Email->eventType(),
            null,
            'shared-across-channels',
            $this->rawPayload(),
        );
        $sms = $this->inbox->persistNew(
            NotificationChannel::Sms,
            'evt-sms',
            NotificationChannel::Sms->eventType(),
            null,
            'shared-across-channels',
            ['to' => ['+580000000000'], 'content' => ['text' => 'hola']],
        );

        $this->assertTrue($email->wasInserted);
        $this->assertTrue($sms->wasInserted);
        $this->assertSame(NotificationChannel::Email, $email->event->channel);
        $this->assertSame(NotificationChannel::Sms, $sms->event->channel);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $eventId, string $idempotencyKey, array $payload): InboxEvent
    {
        return $this->app->make(NotificationDispatchService::class)->dispatch(
            SendEmailMessage::fromArray([
                'event_id' => $eventId,
                'event_type' => 'email.send.requested',
                'idempotency_key' => $idempotencyKey,
                'payload' => $payload,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rawPayload(): array
    {
        return [
            'to' => [['email' => 'user@example.com', 'name' => 'Usuario']],
            'content' => [
                'subject' => 'Hola',
                'text' => 'Cuerpo',
            ],
        ];
    }
}
