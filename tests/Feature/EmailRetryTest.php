<?php

use App\Enums\InboxStatus;
use App\Enums\NotificationChannel;
use App\Repositories\InboxEventRepository;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithMongoInbox;

uses(InteractsWithMongoInbox::class);

beforeEach(function () {
    $this->setUpMongoInbox();
    $this->inbox = $this->app->make(InboxEventRepository::class);
});

it('returns 404 when retrying a missing event', function () {
    $this->postJson('/api/emails/'.Str::uuid().'/retry', [], ['X-API-Key' => 'testing-key'])
        ->assertNotFound()
        ->assertJsonPath('message', 'Evento no encontrado.');
});

it('returns 404 when fetching a missing notification', function () {
    $this->getJson('/api/notifications/'.Str::uuid(), ['X-API-Key' => 'testing-key'])
        ->assertNotFound()
        ->assertJsonPath('message', 'Evento no encontrado.');
});

it('rejects retry when the event is not an email', function () {
    $eventId = (string) Str::uuid();

    $this->inbox->persistNew(
        NotificationChannel::Sms,
        $eventId,
        NotificationChannel::Sms->eventType(),
        null,
        null,
        ['to' => ['+580000000000'], 'content' => ['text' => 'hola']],
    );

    $this->postJson("/api/emails/{$eventId}/retry", [], ['X-API-Key' => 'testing-key'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'El evento no pertenece al canal email.');
});

it('rejects retry when the email was already sent', function () {
    $eventId = (string) Str::uuid();

    $this->postJson('/api/emails', [
        'event_id' => $eventId,
        'payload' => retryEmailPayload(),
    ], ['X-API-Key' => 'testing-key'])->assertAccepted();

    $this->postJson("/api/emails/{$eventId}/retry", [], ['X-API-Key' => 'testing-key'])
        ->assertConflict()
        ->assertJsonPath('message', 'El evento ya fue enviado.');
});

it('retries a failed email and sends it', function () {
    $eventId = (string) Str::uuid();

    $persist = $this->inbox->persistNew(
        NotificationChannel::Email,
        $eventId,
        NotificationChannel::Email->eventType(),
        null,
        null,
        retryEmailPayload(),
    );
    $this->inbox->markFailed($persist->event, 'timeout', true);

    $this->postJson("/api/emails/{$eventId}/retry", [], ['X-API-Key' => 'testing-key'])
        ->assertAccepted()
        ->assertJsonPath('event_id', $eventId)
        ->assertJsonPath('status', InboxStatus::Sent->value)
        ->assertJsonPath('resolved_provider', 'log');
});

it('creates inbox indexes via artisan', function () {
    $this->artisan('inbox:ensure-indexes')
        ->expectsOutput('Índices del inbox verificados.')
        ->assertSuccessful();
});

/**
 * @return array<string, mixed>
 */
function retryEmailPayload(): array
{
    return [
        'to' => [['email' => 'user@example.com', 'name' => 'Usuario']],
        'content' => [
            'subject' => 'Hola',
            'text' => 'Cuerpo',
        ],
    ];
}
