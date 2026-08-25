<?php

use App\Messenger\MessengerFactory;
use Symfony\Component\Messenger\Worker;

it('fails when the consume transport is not configured', function () {
    $this->artisan('messenger:consume', ['transport' => 'unknown'])
        ->expectsOutput('Transporte [unknown] no configurado.')
        ->assertFailed();
});

it('fails when the transport is not consumed in v1', function () {
    $this->artisan('messenger:consume', ['transport' => 'push'])
        ->expectsOutput('El transporte [push] no se consume en v1. Las colas quedan declaradas para los productores.')
        ->assertFailed();
});

it('sets up messenger topology through the factory', function () {
    $messenger = Mockery::mock(MessengerFactory::class);
    $messenger->shouldReceive('setupTopology')->once();
    $this->app->instance(MessengerFactory::class, $messenger);

    $this->artisan('messenger:setup')
        ->expectsOutput('Topología Messenger verificada (email, push, sms y DLQ).')
        ->assertSuccessful();
});

it('consumes with a mocked worker and optional message limit', function () {
    $worker = Mockery::mock(Worker::class);
    $worker->shouldReceive('run')->once()->with([
        'sleep' => 1_000_000,
        'time_limit' => 3600,
        'fetch_size' => 1,
    ]);

    $messenger = Mockery::mock(MessengerFactory::class);
    $messenger->shouldReceive('setupTopology')->once();
    $messenger->shouldReceive('worker')->once()->with('email', 2)->andReturn($worker);
    $this->app->instance(MessengerFactory::class, $messenger);

    $this->artisan('messenger:consume', [
        'transport' => 'email',
        '--limit' => '2',
    ])
        ->expectsOutput('Consumiendo [email] (cola email.send).')
        ->assertSuccessful();
});
