<?php

use App\Messenger\MessengerFactory;
use Illuminate\Support\Facades\DB;

it('requires an api key', function () {
    $this->postJson('/api/emails', [])->assertUnauthorized();
});

it('lists email templates', function () {
    $this->getJson('/api/templates?channel=email', ['X-API-Key' => 'testing-key'])
        ->assertOk()
        ->assertJsonPath('channel', 'email')
        ->assertJsonFragment(['name' => 'welcome']);
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
