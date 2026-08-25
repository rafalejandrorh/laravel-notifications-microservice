<?php

use App\Messenger\SimpleServiceLocator;
use Psr\Container\NotFoundExceptionInterface;

it('returns registered services and reports missing ones', function () {
    $locator = new SimpleServiceLocator([
        'email' => 'transport',
        'empty' => null,
    ]);

    expect($locator->has('email'))->toBeTrue();
    expect($locator->get('email'))->toBe('transport');
    expect($locator->has('empty'))->toBeTrue();
    expect($locator->get('empty'))->toBeNull();
    expect($locator->has('sms'))->toBeFalse();
});

it('throws not found when the service is missing', function () {
    expect(fn () => (new SimpleServiceLocator([]))->get('email'))
        ->toThrow(function (RuntimeException $exception): void {
            expect($exception)->toBeInstanceOf(NotFoundExceptionInterface::class);
            expect($exception->getMessage())->toBe('Service [email] was not found.');
        });
});
