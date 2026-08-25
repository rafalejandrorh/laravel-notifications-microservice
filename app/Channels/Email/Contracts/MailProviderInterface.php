<?php

namespace App\Channels\Email\Contracts;

use App\Channels\Email\RenderedEmail;
use App\Channels\ProviderResult;

interface MailProviderInterface
{
    public function name(): string;

    public function send(RenderedEmail $message): ProviderResult;
}
