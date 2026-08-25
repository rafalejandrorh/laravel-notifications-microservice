<?php

use App\Channels\Email\Adapters\LogMailAdapter;
use App\Exceptions\TransientNotificationException;
use Illuminate\Support\Facades\Mail;

it('sends with cc, bcc and reply-to through the log mailer', function () {
    $result = (new LogMailAdapter)->send(makeRenderedEmail([
        'cc' => [['email' => 'cc@example.com', 'name' => 'CC']],
        'bcc' => [['email' => 'bcc@example.com', 'name' => null]],
        'replyTo' => ['email' => 'reply@example.com', 'name' => 'Reply'],
        'html' => '<p>Hola</p>',
        'text' => null,
    ]));

    expect($result->provider)->toBe('log');
    expect($result->messageId)->not->toBeNull();
});

it('wraps transport failures as transient', function () {
    Mail::shouldReceive('mailer')
        ->once()
        ->with('log')
        ->andThrow(new RuntimeException('smtp timeout'));

    (new LogMailAdapter)->send(makeRenderedEmail());
})->throws(TransientNotificationException::class, 'Fallo al enviar con [log]: smtp timeout');
