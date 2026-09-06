<?php

namespace App\Channels\Email;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class SymfonyEmailFactory
{
    public function make(RenderedEmail $message): Email
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

        foreach ($message->inlineImages as $image) {
            $email->embedFromPath($image['path'], $image['name'], $image['mime']);
        }

        return $email;
    }
}
