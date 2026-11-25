<?php

use App\Contracts\NotificationQueue;
use App\Enums\InboxStatus;
use App\Message\SendEmailMessage;
use App\Models\InboxEvent;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithMongoInbox;

uses(InteractsWithMongoInbox::class);

beforeEach(function () {
    $this->setUpMongoInbox();
    $this->published = fakeNotificationQueue();
});

it('enqueues a templated email', function () {
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
        ->assertJsonPath('status', InboxStatus::Received->value)
        ->assertJsonPath('resolved_provider', null)
        ->assertJsonPath('resolved_template', null);

    expect($this->published->messages)->toHaveCount(1);
    expect($this->published->messages[0])->toBeInstanceOf(SendEmailMessage::class);
    expect($this->published->messages[0]->eventId)->toBe($eventId);

    $event = InboxEvent::query()->where('event_id', $eventId)->first();
    expect($event?->payload)->not->toHaveKey('from');
    expect($event?->payload)->not->toHaveKey('provider');

    $this->getJson("/api/notifications/{$eventId}", ['X-API-Key' => 'testing-key'])
        ->assertOk()
        ->assertJsonPath('status', InboxStatus::Received->value)
        ->assertJsonPath('resolved_provider', null)
        ->assertJsonMissingPath('payload.from');
});

it('enqueues a raw content email', function () {
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
        ->assertJsonPath('status', InboxStatus::Received->value)
        ->assertJsonPath('resolved_template', null);

    expect($this->published->messages)->toHaveCount(1);
});

it('enqueues sivacrim templated emails', function (string $name, array $params) {
    $eventId = (string) Str::uuid();

    $this->postJson('/api/emails', [
        'event_id' => $eventId,
        'payload' => [
            'to' => [['email' => 'user@example.com']],
            'template' => [
                'name' => $name,
                'params' => $params,
            ],
        ],
    ], ['X-API-Key' => 'testing-key'])
        ->assertAccepted()
        ->assertJsonPath('status', InboxStatus::Received->value);

    expect($this->published->messages)->toHaveCount(1);

    $event = InboxEvent::query()->where('event_id', $eventId)->first();
    expect($event?->payload['template']['name'] ?? null)->toBe($name);
})->with([
    ['sivacrim-login-code', ['primer_nombre' => 'Ana', 'code' => 'ABC123']],
    ['sivacrim-email-validation', ['code' => 'XYZ789']],
    ['sivacrim-password-reset', ['reset_url' => 'https://sivacrim.test/reset', 'expire_minutes' => '60']],
]);

it('enqueues emails even when template params are missing', function () {
    $this->postJson('/api/emails', [
        'payload' => [
            'to' => [['email' => 'user@example.com']],
            'template' => [
                'name' => 'sivacrim-login-code',
                'params' => ['code' => 'ABC123'],
            ],
        ],
    ], ['X-API-Key' => 'testing-key'])
        ->assertAccepted()
        ->assertJsonPath('status', InboxStatus::Received->value);

    expect($this->published->messages)->toHaveCount(1);
});

it('returns 503 when the queue publish fails', function () {
    $queue = Mockery::mock(NotificationQueue::class);
    $queue->shouldReceive('publish')->once()->andThrow(new RuntimeException('amqp down'));
    $this->app->instance(NotificationQueue::class, $queue);

    $eventId = (string) Str::uuid();

    $this->postJson('/api/emails', [
        'event_id' => $eventId,
        'payload' => [
            'to' => [['email' => 'user@example.com']],
            'content' => ['subject' => 'Hola', 'text' => 'Cuerpo'],
        ],
    ], ['X-API-Key' => 'testing-key'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'No se pudo encolar el evento.');

    expect(InboxEvent::query()->where('event_id', $eventId)->first()?->status)
        ->toBe(InboxStatus::Received);
});
