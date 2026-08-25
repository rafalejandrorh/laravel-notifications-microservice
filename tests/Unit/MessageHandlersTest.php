<?php

use App\Exceptions\PermanentNotificationException;
use App\Message\SendEmailMessage;
use App\Message\SendPushMessage;
use App\Message\SendSmsMessage;
use App\Message\UnsupportedNotificationMessage;
use App\MessageHandler\SendEmailMessageHandler;
use App\MessageHandler\SendPushMessageHandler;
use App\MessageHandler\SendSmsMessageHandler;
use App\MessageHandler\UnsupportedNotificationMessageHandler;
use App\Models\InboxEvent;
use App\Services\NotificationDispatchService;

it('dispatches email messages', function () {
    $message = SendEmailMessage::fromArray([
        'event_id' => '550e8400-e29b-41d4-a716-446655440000',
        'payload' => [],
    ]);

    $dispatcher = Mockery::mock(NotificationDispatchService::class);
    $dispatcher->shouldReceive('dispatch')->once()->with($message)->andReturn(new InboxEvent);

    (new SendEmailMessageHandler($dispatcher))($message);
});

it('dispatches push messages', function () {
    $message = SendPushMessage::fromArray([
        'event_id' => '550e8400-e29b-41d4-a716-446655440001',
        'payload' => [],
    ]);

    $dispatcher = Mockery::mock(NotificationDispatchService::class);
    $dispatcher->shouldReceive('dispatch')->once()->with($message)->andReturn(new InboxEvent);

    (new SendPushMessageHandler($dispatcher))($message);
});

it('dispatches sms messages', function () {
    $message = SendSmsMessage::fromArray([
        'event_id' => '550e8400-e29b-41d4-a716-446655440002',
        'payload' => [],
    ]);

    $dispatcher = Mockery::mock(NotificationDispatchService::class);
    $dispatcher->shouldReceive('dispatch')->once()->with($message)->andReturn(new InboxEvent);

    (new SendSmsMessageHandler($dispatcher))($message);
});

it('rejects unsupported messages as permanent failures', function () {
    (new UnsupportedNotificationMessageHandler)(
        new UnsupportedNotificationMessage(['event_id' => 'x'], 'Tipo de evento no soportado: fax.send.requested.'),
    );
})->throws(PermanentNotificationException::class, 'Tipo de evento no soportado: fax.send.requested.');
