<?php

use App\Channels\ChannelRegistry;
use App\Channels\Email\EmailChannel;
use App\Enums\NotificationChannel;

it('returns the registered channel implementation', function () {
    $email = Mockery::mock(EmailChannel::class);
    $registry = new ChannelRegistry([
        NotificationChannel::Email->value => $email,
    ]);

    expect($registry->for(NotificationChannel::Email))->toBe($email);
});

it('fails when the channel has no implementation', function () {
    $registry = new ChannelRegistry([]);

    $registry->for(NotificationChannel::Push);
})->throws(InvalidArgumentException::class, 'No hay implementación para el canal [push].');
