<?php

namespace App\Channels\Email\Adapters;

class SmtpMailAdapter extends LaravelMailAdapter
{
    public function __construct(string $mailer = 'smtp')
    {
        parent::__construct($mailer);
    }

    public function name(): string
    {
        return $this->mailer;
    }
}
