<?php

namespace Tests\Unit;

use App\Message\SendEmailMessage;
use App\Message\SendPushMessage;
use App\Message\UnsupportedNotificationMessage;
use App\Messenger\JsonMessageSerializer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Envelope;
use Tests\TestCase;

class JsonMessageSerializerTest extends TestCase
{
    private JsonMessageSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new JsonMessageSerializer;
    }

    #[Test]
    public function it_round_trips_an_email_message(): void
    {
        $message = SendEmailMessage::fromArray([
            'event_id' => '550e8400-e29b-41d4-a716-446655440000',
            'event_type' => 'email.send.requested',
            'idempotency_key' => 'case-123:welcome',
            'payload' => [
                'to' => [['email' => 'user@example.com']],
                'content' => ['subject' => 'Hola', 'text' => 'Cuerpo'],
            ],
        ]);

        $encoded = $this->serializer->encode(new Envelope($message));
        $decoded = $this->serializer->decode($encoded)->getMessage();

        $this->assertInstanceOf(SendEmailMessage::class, $decoded);
        $this->assertSame($message->eventId, $decoded->eventId);
        $this->assertSame('application/json', $encoded['headers']['Content-Type']);
        $this->assertSame('email.send.requested', $encoded['headers']['type']);
        $this->assertStringNotContainsString('O:', $encoded['body']);
    }

    #[Test]
    public function it_maps_push_event_type_to_push_dto(): void
    {
        $decoded = $this->serializer->decode([
            'body' => json_encode([
                'event_id' => '550e8400-e29b-41d4-a716-446655440000',
                'event_type' => 'push.send.requested',
                'payload' => ['tokens' => ['abc']],
            ]),
        ])->getMessage();

        $this->assertInstanceOf(SendPushMessage::class, $decoded);
    }

    #[Test]
    public function it_maps_unknown_event_types_to_unsupported_message(): void
    {
        $decoded = $this->serializer->decode([
            'body' => json_encode([
                'event_id' => '550e8400-e29b-41d4-a716-446655440000',
                'event_type' => 'fax.send.requested',
                'payload' => [],
            ]),
        ])->getMessage();

        $this->assertInstanceOf(UnsupportedNotificationMessage::class, $decoded);
        $this->assertStringContainsString('fax.send.requested', $decoded->reason);
    }
}
