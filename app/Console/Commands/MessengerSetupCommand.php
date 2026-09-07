<?php

namespace App\Console\Commands;

use App\Enums\QueueDriver;
use App\Messenger\MessengerFactory;
use Illuminate\Console\Command;

class MessengerSetupCommand extends Command
{
    protected $signature = 'messenger:setup';

    protected $description = 'Declara el exchange y las colas email/push/sms (y sus DLQ) en RabbitMQ';

    public function handle(MessengerFactory $messenger): int
    {
        if (! QueueDriver::current()->isRabbitMq()) {
            $this->error('Pub/sub AMQP desactivado (NOTIFICATION_QUEUE_DRIVER no es rabbitmq).');

            return self::FAILURE;
        }

        $messenger->setupTopology();

        $this->info('Topología Messenger verificada (email, push, sms y DLQ).');

        return self::SUCCESS;
    }
}
