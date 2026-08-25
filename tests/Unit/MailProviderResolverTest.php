<?php

use App\Channels\Email\Adapters\FailoverMailAdapter;
use App\Channels\Email\Adapters\GmailMailAdapter;
use App\Channels\Email\Adapters\LogMailAdapter;
use App\Channels\Email\Adapters\MailgunMailAdapter;
use App\Channels\Email\Adapters\SmtpMailAdapter;
use App\Channels\Email\MailProviderResolver;
use App\Exceptions\PermanentNotificationException;

beforeEach(function () {
    $this->resolver = new MailProviderResolver;
});

it('resolves smtp and sendmail to smtp adapters named after the mailer', function () {
    $smtp = $this->resolver->driver('smtp');
    $sendmail = $this->resolver->driver('sendmail');

    expect($smtp)->toBeInstanceOf(SmtpMailAdapter::class);
    expect($smtp->name())->toBe('smtp');
    expect($sendmail)->toBeInstanceOf(SmtpMailAdapter::class);
    expect($sendmail->name())->toBe('sendmail');
});

it('resolves mailgun and gmail drivers', function () {
    expect($this->resolver->driver('mailgun'))->toBeInstanceOf(MailgunMailAdapter::class)
        ->and($this->resolver->driver('mailgun')->name())->toBe('mailgun');

    expect($this->resolver->driver('gmail'))->toBeInstanceOf(GmailMailAdapter::class)
        ->and($this->resolver->driver('gmail')->name())->toBe('gmail');
});

it('resolves log and array to the log adapter', function () {
    expect($this->resolver->driver('log'))->toBeInstanceOf(LogMailAdapter::class)
        ->and($this->resolver->driver('log')->name())->toBe('log');

    expect($this->resolver->driver('array'))->toBeInstanceOf(LogMailAdapter::class)
        ->and($this->resolver->driver('array')->name())->toBe('log');
});

it('rejects unsupported mailers', function () {
    $this->resolver->driver('ses');
})->throws(PermanentNotificationException::class, 'Mailer [ses] no está soportado.');

it('uses the default mailer when none is given', function () {
    config(['mail.default' => 'log', 'email.failover_mailer' => null]);

    expect($this->resolver->resolve())->toBeInstanceOf(LogMailAdapter::class);
});

it('wraps a distinct failover mailer', function () {
    config(['email.failover_mailer' => 'log']);

    $resolved = $this->resolver->resolve('smtp');

    expect($resolved)->toBeInstanceOf(FailoverMailAdapter::class);
    expect($resolved->name())->toBe('smtp');
});

it('does not wrap failover when it matches the primary mailer', function () {
    config(['email.failover_mailer' => 'smtp']);

    expect($this->resolver->resolve('smtp'))->toBeInstanceOf(SmtpMailAdapter::class);
});
