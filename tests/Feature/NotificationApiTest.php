<?php

use App\Enums\InboxStatus;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithMongoInbox;

uses(InteractsWithMongoInbox::class);

beforeEach(fn () => $this->setUpMongoInbox());

it('sends a templated email', function () {
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
});

it('sends raw content email', function () {
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
});

it('fails permanently when template params are missing', function () {
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
});
