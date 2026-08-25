<?php

it('requires an api key', function () {
    $this->postJson('/api/emails', [])->assertUnauthorized();
});

it('lists email templates', function () {
    $this->getJson('/api/templates?channel=email', ['X-API-Key' => 'testing-key'])
        ->assertOk()
        ->assertJsonPath('channel', 'email')
        ->assertJsonFragment(['name' => 'welcome']);
});

it('reports dependency checks in health', function () {
    $this->getJson('/api/health')
        ->assertJsonStructure(['status', 'checks' => ['app', 'mongodb', 'rabbitmq']]);
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
