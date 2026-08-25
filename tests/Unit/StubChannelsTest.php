<?php

use App\Channels\Push\PushChannel;
use App\Channels\Sms\SmsChannel;
use App\Exceptions\ChannelNotEnabledException;
use App\Exceptions\TransientNotificationException;
use App\Models\InboxEvent;

it('marks push as unsupported and rejects render and send', function () {
    $channel = new PushChannel;
    $event = new InboxEvent;

    expect($channel->supported())->toBeFalse();
    expect(fn () => $channel->render($event))->toThrow(ChannelNotEnabledException::class, 'Canal [push] no habilitado.');
    expect(fn () => $channel->send($event))->toThrow(ChannelNotEnabledException::class, 'Canal [push] no habilitado.');
});

it('marks sms as unsupported and rejects render and send', function () {
    $channel = new SmsChannel;
    $event = new InboxEvent;

    expect($channel->supported())->toBeFalse();
    expect(fn () => $channel->render($event))->toThrow(ChannelNotEnabledException::class, 'Canal [sms] no habilitado.');
    expect(fn () => $channel->send($event))->toThrow(ChannelNotEnabledException::class, 'Canal [sms] no habilitado.');
});

it('does not delay retries on transient failures', function () {
    expect((new TransientNotificationException('timeout'))->getRetryDelay())->toBeNull();
});
