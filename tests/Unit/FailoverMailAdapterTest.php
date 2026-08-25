<?php

use App\Channels\Email\Adapters\FailoverMailAdapter;
use App\Channels\Email\Contracts\MailProviderInterface;
use App\Channels\ProviderResult;
use App\Exceptions\PermanentNotificationException;
use App\Exceptions\TransientNotificationException;

it('returns the primary result when the first send succeeds', function () {
    $primary = Mockery::mock(MailProviderInterface::class);
    $primary->shouldReceive('name')->andReturn('smtp');
    $primary->shouldReceive('send')->once()->andReturn(new ProviderResult('smtp', 'id-1'));

    $fallback = Mockery::mock(MailProviderInterface::class);
    $fallback->shouldNotReceive('send');

    $result = (new FailoverMailAdapter($primary, $fallback))->send(makeRenderedEmail());

    expect($result->provider)->toBe('smtp');
    expect($result->messageId)->toBe('id-1');
});

it('exposes the primary provider name', function () {
    $primary = Mockery::mock(MailProviderInterface::class);
    $primary->shouldReceive('name')->andReturn('mailgun');
    $fallback = Mockery::mock(MailProviderInterface::class);

    expect((new FailoverMailAdapter($primary, $fallback))->name())->toBe('mailgun');
});

it('does not fall back on a permanent failure', function () {
    $primary = Mockery::mock(MailProviderInterface::class);
    $primary->shouldReceive('send')->once()->andThrow(new PermanentNotificationException('rechazado'));

    $fallback = Mockery::mock(MailProviderInterface::class);
    $fallback->shouldNotReceive('send');

    (new FailoverMailAdapter($primary, $fallback))->send(makeRenderedEmail());
})->throws(PermanentNotificationException::class, 'rechazado');

it('falls back when the primary fails transiently', function () {
    $primary = Mockery::mock(MailProviderInterface::class);
    $primary->shouldReceive('name')->andReturn('smtp');
    $primary->shouldReceive('send')->once()->andThrow(new TransientNotificationException('timeout'));

    $fallback = Mockery::mock(MailProviderInterface::class);
    $fallback->shouldReceive('send')->once()->andReturn(new ProviderResult('log', 'id-2'));

    $result = (new FailoverMailAdapter($primary, $fallback))->send(makeRenderedEmail());

    expect($result->provider)->toBe('smtp+log');
    expect($result->messageId)->toBe('id-2');
});

it('rethrows the original transient error when the fallback also fails transiently', function () {
    $primary = Mockery::mock(MailProviderInterface::class);
    $primary->shouldReceive('send')->once()->andThrow(new TransientNotificationException('primary down'));

    $fallback = Mockery::mock(MailProviderInterface::class);
    $fallback->shouldReceive('send')->once()->andThrow(new TransientNotificationException('fallback down'));

    (new FailoverMailAdapter($primary, $fallback))->send(makeRenderedEmail());
})->throws(TransientNotificationException::class, 'primary down');
