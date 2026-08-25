<?php

namespace App\Channels\Email\Adapters;

class MailgunMailAdapter extends LaravelMailAdapter
{
    public function __construct()
    {
        parent::__construct('mailgun');
    }

    public function name(): string
    {
        return 'mailgun';
    }
}
