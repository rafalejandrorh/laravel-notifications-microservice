<?php

namespace App\Channels\Email;

use App\Channels\Email\Adapters\FailoverMailAdapter;
use App\Channels\Email\Adapters\GmailMailAdapter;
use App\Channels\Email\Adapters\LogMailAdapter;
use App\Channels\Email\Adapters\MailgunMailAdapter;
use App\Channels\Email\Adapters\SmtpMailAdapter;
use App\Channels\Email\Contracts\MailProviderInterface;
use App\Exceptions\PermanentNotificationException;

class MailProviderResolver
{
    public function resolve(?string $mailer = null): MailProviderInterface
    {
        $mailer ??= (string) config('mail.default', 'log');
        $primary = $this->driver($mailer);
        $failover = config('email.failover_mailer');

        if (filled($failover) && $failover !== $mailer) {
            return new FailoverMailAdapter($primary, $this->driver((string) $failover));
        }

        return $primary;
    }

    public function driver(string $mailer): MailProviderInterface
    {
        return match ($mailer) {
            'smtp', 'sendmail' => new SmtpMailAdapter($mailer),
            'mailgun' => new MailgunMailAdapter,
            'gmail' => new GmailMailAdapter,
            'log', 'array' => new LogMailAdapter($mailer === 'array' ? 'array' : 'log'),
            default => throw new PermanentNotificationException("Mailer [{$mailer}] no está soportado."),
        };
    }
}
