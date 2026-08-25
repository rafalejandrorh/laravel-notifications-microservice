<?php

use App\Enums\NotificationChannel;

it('exposes event type and routing key per channel', function () {
    expect(NotificationChannel::Email->eventType())->toBe('email.send.requested');
    expect(NotificationChannel::Email->routingKey())->toBe('email.send');
    expect(NotificationChannel::Push->eventType())->toBe('push.send.requested');
    expect(NotificationChannel::Sms->routingKey())->toBe('sms.send');
});
