<?php

namespace App\Channels\Email\Adapters;

use App\Channels\Email\Contracts\MailProviderInterface;
use App\Channels\Email\RenderedEmail;
use App\Channels\ProviderResult;
use App\Exceptions\PermanentNotificationException;
use App\Exceptions\TransientNotificationException;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class GmailMailAdapter implements MailProviderInterface
{
    public function name(): string
    {
        return 'gmail';
    }

    public function send(RenderedEmail $message): ProviderResult
    {
        if (! class_exists(Client::class)) {
            throw new PermanentNotificationException('El adaptador Gmail requiere el paquete google/apiclient.');
        }

        $credentials = $this->credentials();
        $delegatedUser = (string) config('email.gmail.delegated_user');

        if ($delegatedUser === '') {
            throw new PermanentNotificationException('GMAIL_DELEGATED_USER no está configurado.');
        }

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
        }

        try {
            $client = new Client;
            $client->setAuthConfig($credentials);
            $client->setSubject($delegatedUser);
            $client->setScopes([Gmail::GMAIL_SEND]);

            $gmail = new Gmail($client);
            $gmailMessage = new Message;
            $gmailMessage->setRaw($this->base64UrlEncode($email->toString()));

            $sent = $gmail->users_messages->send('me', $gmailMessage);
        } catch (PermanentNotificationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TransientNotificationException(
                "Fallo al enviar con [gmail]: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        return new ProviderResult($this->name(), $sent->getId());
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $value = (string) config('email.gmail.service_account_json');

        if ($value === '') {
            throw new PermanentNotificationException('GMAIL_SERVICE_ACCOUNT_JSON no está configurado.');
        }

        if (is_file($value)) {
            $decoded = json_decode((string) file_get_contents($value), true);
        } else {
            $decoded = json_decode($value, true);
        }

        if (! is_array($decoded)) {
            throw new PermanentNotificationException('GMAIL_SERVICE_ACCOUNT_JSON no es un JSON válido.');
        }

        return $decoded;
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
