<?php

namespace App\Channels\Email\Adapters;

use App\Channels\Email\Contracts\MailProviderInterface;
use App\Channels\Email\RenderedEmail;
use App\Channels\Email\SymfonyEmailFactory;
use App\Channels\ProviderResult;
use App\Exceptions\TransientNotificationException;
use Illuminate\Support\Facades\Mail;
use Throwable;

abstract class LaravelMailAdapter implements MailProviderInterface
{
    public function __construct(
        protected string $mailer,
        private SymfonyEmailFactory $emails = new SymfonyEmailFactory,
    ) {}

    public function send(RenderedEmail $message): ProviderResult
    {
        $email = $this->emails->make($message);

        try {
            $sent = Mail::mailer($this->mailer)->getSymfonyTransport()->send($email);
        } catch (Throwable $exception) {
            throw new TransientNotificationException(
                "Fallo al enviar con [{$this->name()}]: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        return new ProviderResult($this->name(), $sent?->getMessageId());
    }
}
