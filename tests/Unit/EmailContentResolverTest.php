<?php

use App\Channels\Email\EmailContentResolver;
use App\Channels\Email\TemplateCatalog;
use App\Channels\Email\TemplateRenderer;
use App\Enums\NotificationChannel;
use App\Exceptions\PermanentNotificationException;
use App\Models\InboxEvent;

beforeEach(function () {
    $this->resolver = new EmailContentResolver(
        new TemplateCatalog,
        new TemplateRenderer,
    );
});

it('renders welcome template and uses latest when version is omitted', function () {
    $rendered = $this->resolver->resolve(emailInboxEvent([
        'to' => [['email' => 'user@example.com']],
        'template' => [
            'name' => 'welcome',
            'params' => ['name' => 'Juan'],
        ],
    ]));

    expect($rendered->templateName)->toBe('welcome');
    expect($rendered->templateVersion)->toBe(1);
    expect($rendered->content['subject'])->toBe('Bienvenido, Juan');
    expect($rendered->content['html'])->toContain('Juan');
});

it('renders raw content without blade', function () {
    $rendered = $this->resolver->resolve(emailInboxEvent([
        'to' => [['email' => 'user@example.com']],
        'content' => [
            'subject' => 'Asunto crudo',
            'html' => '<p>Hola</p>',
        ],
    ]));

    expect($rendered->templateName)->toBeNull();
    expect($rendered->content['subject'])->toBe('Asunto crudo');
    expect($rendered->content['html'])->toBe('<p>Hola</p>');
});

it('rejects template and content together', function () {
    $this->resolver->resolve(emailInboxEvent([
        'to' => [['email' => 'user@example.com']],
        'template' => ['name' => 'welcome', 'params' => ['name' => 'Juan']],
        'content' => ['subject' => 'X', 'text' => 'Y'],
    ]));
})->throws(PermanentNotificationException::class);

it('rejects missing required params', function () {
    $this->resolver->resolve(emailInboxEvent([
        'to' => [['email' => 'user@example.com']],
        'template' => ['name' => 'welcome', 'params' => []],
    ]));
})->throws(PermanentNotificationException::class, 'Faltan parámetros de plantilla: name.');

it('rejects unknown template versions', function () {
    $this->resolver->resolve(emailInboxEvent([
        'to' => [['email' => 'user@example.com']],
        'template' => ['name' => 'welcome', 'version' => 99, 'params' => ['name' => 'Juan']],
    ]));
})->throws(PermanentNotificationException::class);

/**
 * @param  array<string, mixed>  $payload
 */
function emailInboxEvent(array $payload): InboxEvent
{
    return new InboxEvent([
        'event_id' => '550e8400-e29b-41d4-a716-446655440000',
        'channel' => NotificationChannel::Email,
        'payload' => $payload,
    ]);
}
