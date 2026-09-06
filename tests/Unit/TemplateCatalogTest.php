<?php

use App\Channels\Email\TemplateCatalog;
use App\Channels\Email\TemplateRenderer;
use App\Enums\NotificationChannel;
use App\Exceptions\PermanentNotificationException;

it('returns the full catalog when no channel is given', function () {
    $all = (new TemplateCatalog)->all();

    expect($all)->toHaveKeys(['email', 'push', 'sms']);
    expect($all['email'])->toHaveKey('welcome')
        ->and($all['email'])->toHaveKeys([
            'sivacrim-login-code',
            'sivacrim-email-validation',
            'sivacrim-password-reset',
        ]);
});

it('resolves sivacrim templates with required params', function (string $name, array $required) {
    $resolved = (new TemplateCatalog)->resolve(NotificationChannel::Email, $name, null);

    expect($resolved['name'])->toBe($name)
        ->and($resolved['version'])->toBe(1)
        ->and($resolved['from_identity'])->toBe('notificaciones')
        ->and($resolved['required_params'])->toBe($required)
        ->and($resolved['view'])->toBe("notifications.email.{$name}.v1");
})->with([
    ['sivacrim-login-code', ['primer_nombre', 'code']],
    ['sivacrim-email-validation', ['code']],
    ['sivacrim-password-reset', ['reset_url', 'expire_minutes']],
]);

it('merges sivacrim templates from the dedicated config file', function () {
    $sivacrim = require config_path('sivacrim_notification_templates.php');

    expect($sivacrim)->toHaveKeys([
        'sivacrim-login-code',
        'sivacrim-email-validation',
        'sivacrim-password-reset',
    ]);

    $email = (new TemplateCatalog)->all(NotificationChannel::Email);

    expect($email['sivacrim-login-code'])->toBe($sivacrim['sivacrim-login-code'])
        ->and($email)->toHaveKey('welcome');
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
