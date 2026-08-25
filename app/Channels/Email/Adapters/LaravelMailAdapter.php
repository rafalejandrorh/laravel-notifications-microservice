<?php

namespace App\Channels\Email\Adapters;

use App\Channels\Email\Contracts\MailProviderInterface;
use App\Channels\Email\RenderedEmail;
use App\Channels\ProviderResult;
use App\Exceptions\TransientNotificationException;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

abstract class LaravelMailAdapter implements MailProviderInterface
{
    public function __construct(
        protected string $mailer,
    ) {}

    public function send(RenderedEmail $message): ProviderResult
    {
        $email = (new Email)
            ->from(new Address($message->from['address'], (string) ($message->from['name'] ?? '')))
            ->subject($message->subject);

        foreach ($message->to as $recipient) {
            $email->addTo(new Address($recipient['email'], (string) ($recipient['name'] ?? '')));
        }

        foreach ($message->cc as $recipient) {
            $email->addCc(new Address($recipient['email'], (string) ($recipient['name'] ?? '')));
        }

        foreach ($message->bcc as $recipient) {
            $email->addBcc(new Address($recipient['email'], (string) ($recipient['name'] ?? '')));
        }

        if ($message->replyTo !== null) {
            $email->replyTo(new Address($message->replyTo['email'], (string) ($message->replyTo['name'] ?? '')));
        }

        if (filled($message->html)) {
            $email->html($message->html);
        }

        if (filled($message->text)) {
            $email->text($message->text);
        } elseif (filled($message->html)) {
            $email->text(trim(html_entity_decode(strip_tags($message->html))));
        }

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
