<?php

namespace Tests\Feature;

use App\Enums\InboxStatus;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithMongoInbox;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use InteractsWithMongoInbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMongoInbox();
    }

    #[Test]
    public function it_sends_a_templated_email(): void
    {
        $eventId = (string) Str::uuid();

        $this->postJson('/api/emails', [
            'event_id' => $eventId,
            'event_type' => 'email.send.requested',
            'idempotency_key' => 'case-123:welcome',
            'payload' => [
                'to' => [['email' => 'user@example.com', 'name' => 'Usuario']],
                'template' => [
                    'name' => 'welcome',
                    'params' => ['name' => 'Juan'],
                ],
                'provider' => 'smtp',
                'from' => 'attacker@example.com',
            ],
        ], ['X-API-Key' => 'testing-key'])
            ->assertAccepted()
            ->assertJsonPath('event_id', $eventId)
            ->assertJsonPath('channel', 'email')
            ->assertJsonPath('status', InboxStatus::Sent->value)
            ->assertJsonPath('resolved_provider', 'log')
            ->assertJsonPath('resolved_template', 'welcome')
            ->assertJsonPath('resolved_version', 1);

        $this->getJson("/api/notifications/{$eventId}", ['X-API-Key' => 'testing-key'])
            ->assertOk()
            ->assertJsonPath('resolved_provider', 'log')
            ->assertJsonMissingPath('payload.from');
    }

    #[Test]
    public function it_sends_raw_content_email(): void
    {
        $eventId = (string) Str::uuid();

        $this->postJson('/api/emails', [
            'event_id' => $eventId,
            'payload' => [
                'to' => [['email' => 'user@example.com']],
                'content' => [
                    'subject' => 'Asunto crudo',
                    'html' => '<p>Hola</p>',
                ],
            ],
        ], ['X-API-Key' => 'testing-key'])
            ->assertAccepted()
            ->assertJsonPath('status', InboxStatus::Sent->value)
            ->assertJsonPath('resolved_template', null);
    }

    #[Test]
    public function missing_template_params_fail_permanently(): void
    {
        $this->postJson('/api/emails', [
            'payload' => [
                'to' => [['email' => 'user@example.com']],
                'template' => [
                    'name' => 'welcome',
                    'params' => [],
                ],
            ],
        ], ['X-API-Key' => 'testing-key'])
            ->assertAccepted()
            ->assertJsonPath('status', InboxStatus::Failed->value)
            ->assertJsonPath('retryable', false);
    }
}
