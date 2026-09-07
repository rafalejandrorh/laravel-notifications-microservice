<?php

use App\Exceptions\PermanentNotificationException;
use App\Exceptions\TransientNotificationException;
use App\Messenger\RecoverableNotificationException;
use App\Messenger\UnrecoverableNotificationException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

it('keeps domain exceptions free of messenger interfaces', function () {
    expect(new PermanentNotificationException('x'))->not->toBeInstanceOf(UnrecoverableExceptionInterface::class);
    expect(new TransientNotificationException('x'))->not->toBeInstanceOf(RecoverableExceptionInterface::class);
});

it('maps permanent failures at the messenger edge', function () {
    $exception = new UnrecoverableNotificationException('contrato inválido');

    expect($exception)->toBeInstanceOf(PermanentNotificationException::class)
        ->and($exception)->toBeInstanceOf(UnrecoverableExceptionInterface::class);
});

it('maps transient failures at the messenger edge without a custom delay', function () {
    $exception = new RecoverableNotificationException('timeout');

    expect($exception)->toBeInstanceOf(TransientNotificationException::class)
        ->and($exception)->toBeInstanceOf(RecoverableExceptionInterface::class)
        ->and($exception->getRetryDelay())->toBeNull();
});
