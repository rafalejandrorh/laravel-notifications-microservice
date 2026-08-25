<?php

namespace App\Console\Commands;

use App\Messenger\MessengerFactory;
use Illuminate\Console\Command;

class MessengerSetupCommand extends Command
{
    protected $signature = 'messenger:setup';

    protected $description = 'Declara el exchange y las colas email/push/sms (y sus DLQ) en RabbitMQ';

    public function handle(MessengerFactory $messenger): int
    {
        $messenger->setupTopology();

        $this->info('Topología Messenger verificada (email, push, sms y DLQ).');

        return self::SUCCESS;
    }
}
