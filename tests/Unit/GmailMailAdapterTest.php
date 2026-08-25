<?php

use App\Channels\Email\Adapters\GmailMailAdapter;
use App\Exceptions\PermanentNotificationException;
use App\Exceptions\TransientNotificationException;

beforeEach(function () {
    $this->adapter = new GmailMailAdapter;
});

it('names the provider gmail', function () {
    expect($this->adapter->name())->toBe('gmail');
});

it('rejects missing service account credentials', function () {
    config([
        'email.gmail.service_account_json' => '',
        'email.gmail.delegated_user' => 'user@example.com',
    ]);

    $this->adapter->send(makeRenderedEmail());
})->throws(PermanentNotificationException::class, 'GMAIL_SERVICE_ACCOUNT_JSON no está configurado.');

it('rejects invalid service account json', function () {
    config([
        'email.gmail.service_account_json' => 'not-json',
        'email.gmail.delegated_user' => 'user@example.com',
    ]);

    $this->adapter->send(makeRenderedEmail());
})->throws(PermanentNotificationException::class, 'GMAIL_SERVICE_ACCOUNT_JSON no es un JSON válido.');

it('rejects a missing delegated user', function () {
    config([
        'email.gmail.service_account_json' => json_encode(['type' => 'service_account']),
        'email.gmail.delegated_user' => '',
    ]);

    $this->adapter->send(makeRenderedEmail());
})->throws(PermanentNotificationException::class, 'GMAIL_DELEGATED_USER no está configurado.');

it('loads credentials from a json file and treats google failures as transient', function () {
    $file = tempnam(sys_get_temp_dir(), 'gmail');
    file_put_contents($file, json_encode([
        'type' => 'service_account',
        'client_email' => 'bot@example.com',
        'private_key' => 'not-a-real-key',
    ]));

    config([
        'email.gmail.service_account_json' => $file,
        'email.gmail.delegated_user' => 'user@example.com',
    ]);

    try {
        $this->adapter->send(makeRenderedEmail([
            'cc' => [['email' => 'cc@example.com', 'name' => 'CC']],
            'bcc' => [['email' => 'bcc@example.com', 'name' => null]],
            'replyTo' => ['email' => 'reply@example.com', 'name' => 'Reply'],
            'html' => '<p>Hola</p>',
            'text' => 'Hola',
        ]));
    } finally {
        @unlink($file);
    }
})->throws(TransientNotificationException::class);
