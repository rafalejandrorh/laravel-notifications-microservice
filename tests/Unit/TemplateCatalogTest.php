<?php

use App\Channels\Email\TemplateCatalog;
use App\Channels\Email\TemplateRenderer;
use App\Enums\NotificationChannel;
use App\Exceptions\PermanentNotificationException;

it('returns the full catalog when no channel is given', function () {
    $all = (new TemplateCatalog)->all();

    expect($all)->toHaveKeys(['email', 'push', 'sms']);
    expect($all['email'])->toHaveKey('welcome');
});

it('rejects templates that do not exist', function () {
    (new TemplateCatalog)->resolve(NotificationChannel::Email, 'missing', null);
})->throws(PermanentNotificationException::class, 'Plantilla [missing] no existe en el canal [email].');

it('rejects missing template views', function () {
    (new TemplateRenderer)->render([
        'view' => 'notifications.email.missing.v1',
        'subject' => 'Hola',
        'required_params' => [],
    ], []);
})->throws(PermanentNotificationException::class, 'No existe la vista de plantilla [notifications.email.missing.v1].');

it('derives text from html when the text view is missing', function () {
    $rendered = (new TemplateRenderer)->render([
        'view' => 'welcome',
        'subject' => 'Hola {name}',
        'required_params' => [],
    ], ['name' => 'Juan']);

    expect($rendered['subject'])->toBe('Hola Juan');
    expect($rendered['html'])->not->toBeEmpty();
    expect($rendered['text'])->not->toBeEmpty();
    expect($rendered['subject'])->not->toContain('{name}');
});

it('keeps unknown subject placeholders', function () {
    expect((new TemplateRenderer)->interpolate('Hola {missing}', []))->toBe('Hola {missing}');
});
