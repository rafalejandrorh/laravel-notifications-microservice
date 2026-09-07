<?php

use App\Messenger\MessengerFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('requires an api key', function () {
    $this->postJson('/api/emails', [])->assertUnauthorized();
});

it('lists email templates', function () {
    $this->getJson('/api/templates?channel=email', ['X-API-Key' => 'testing-key'])
        ->assertOk()
        ->assertJsonPath('channel', 'email')
        ->assertJsonFragment(['name' => 'welcome'])
        ->assertJsonFragment(['name' => 'sivacrim-login-code'])
        ->assertJsonFragment(['name' => 'sivacrim-email-validation'])
        ->assertJsonFragment(['name' => 'sivacrim-password-reset']);
});

it('rejects invalid template channels', function () {
    $this->getJson('/api/templates?channel=fax', ['X-API-Key' => 'testing-key'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Canal inválido.');
});

it('reports dependency checks in health', function () {
    $this->getJson('/api/health')
        ->assertJsonStructure(['status', 'checks' => ['app', 'mongodb', 'rabbitmq']]);
});

it('reports laravel queue checks instead of rabbitmq', function () {
    config(['notifications.queue_driver' => 'laravel']);

    $this->getJson('/api/health')
        ->assertJsonStructure(['status', 'checks' => ['app', 'mongodb', 'queue']])
        ->assertJsonMissingPath('checks.rabbitmq');
});

it('reports degraded health when dependencies fail', function () {
    DB::shouldReceive('connection')
        ->with('mongodb')
        ->andThrow(new RuntimeException('mongo down'));

    $messenger = Mockery::mock(MessengerFactory::class);
    $messenger->shouldReceive('transport')->with('email')->andThrow(new RuntimeException('amqp down'));
    $this->app->instance(MessengerFactory::class, $messenger);

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.app', true)
        ->assertJsonPath('checks.mongodb', false)
        ->assertJsonPath('checks.rabbitmq', false);
});

it('reports degraded health when the laravel queue is down', function () {
    config([
        'notifications.queue_driver' => 'laravel',
        'queue.default' => 'redis',
    ]);

    DB::shouldReceive('connection')
        ->with('mongodb')
        ->andThrow(new RuntimeException('mongo down'));

    Queue::shouldReceive('connection')
        ->with('redis')
        ->andThrow(new RuntimeException('redis down'));

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.app', true)
        ->assertJsonPath('checks.mongodb', false)
        ->assertJsonPath('checks.queue', false);
});

it('rejects both template and content', function () {
    $this->postJson('/api/emails', [
        'payload' => [
            'to' => [['email' => 'user@example.com']],
            'template' => ['name' => 'welcome', 'params' => ['name' => 'Juan']],
            'content' => ['subject' => 'X', 'text' => 'Y'],
        ],
    ], ['X-API-Key' => 'testing-key'])
        ->assertUnprocessable();
});
