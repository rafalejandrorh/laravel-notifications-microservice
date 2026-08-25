<?php

namespace App\Channels\Email\Adapters;

use App\Channels\Email\Contracts\MailProviderInterface;
use App\Channels\Email\RenderedEmail;
use App\Channels\ProviderResult;
use App\Exceptions\PermanentNotificationException;
use App\Exceptions\TransientNotificationException;

class FailoverMailAdapter implements MailProviderInterface
{
    public function __construct(
        private MailProviderInterface $primary,
        private MailProviderInterface $fallback,
    ) {}

    public function name(): string
    {
        return $this->primary->name();
    }

    public function send(RenderedEmail $message): ProviderResult
    {
        try {
            return $this->primary->send($message);
        } catch (PermanentNotificationException $exception) {
            throw $exception;
        } catch (TransientNotificationException $exception) {
            try {
                $result = $this->fallback->send($message);
            } catch (TransientNotificationException) {
                throw $exception;
            }

            return new ProviderResult(
                $this->primary->name().'+'.$result->provider,
                $result->messageId,
            );
        }
    }
}
