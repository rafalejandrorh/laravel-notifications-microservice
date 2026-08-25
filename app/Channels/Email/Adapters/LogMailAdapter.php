<?php

namespace App\Channels\Email\Adapters;

class LogMailAdapter extends LaravelMailAdapter
{
    public function __construct(string $mailer = 'log')
    {
        parent::__construct($mailer);
    }

    public function name(): string
    {
        return 'log';
    }
}
