<?php

use App\Message\SendEmailMessage;
use App\MessageHandler\SendEmailMessageHandler;
use App\MessageHandler\SendPushMessageHandler;
use App\MessageHandler\SendSmsMessageHandler;
use App\MessageHandler\UnsupportedNotificationMessageHandler;
use App\Messenger\JsonMessageSerializer;
use App\Messenger\MessengerFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Envelope;

it('sends the envelope to the channel transport', function () {
    $message = SendEmailMessage::fromArray([
        'event_id' => '550e8400-e29b-41d4-a716-446655440000',
        'payload' => [],
    ]);

    $transport = Mockery::mock(AmqpTransport::class);
    $transport->shouldReceive('send')
        ->once()
        ->with(Mockery::on(fn (Envelope $envelope): bool => $envelope->getMessage() === $message))
        ->andReturn(new Envelope($message));

    $factory = Mockery::mock(MessengerFactory::class, [
        Mockery::mock(JsonMessageSerializer::class),
        Mockery::mock(SendEmailMessageHandler::class),
        Mockery::mock(SendPushMessageHandler::class),
        Mockery::mock(SendSmsMessageHandler::class),
        Mockery::mock(UnsupportedNotificationMessageHandler::class),
    ])->makePartial();
    $factory->shouldReceive('transport')->once()->with('email')->andReturn($transport);

    $factory->send($message);
});
