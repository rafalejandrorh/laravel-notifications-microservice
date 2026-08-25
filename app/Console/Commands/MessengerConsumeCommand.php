<?php

namespace App\Console\Commands;

use App\Messenger\MessengerFactory;
use Illuminate\Console\Command;
use Symfony\Component\Messenger\Worker;

class MessengerConsumeCommand extends Command
{
    protected $signature = 'messenger:consume
                            {transport=email : Transporte a consumir (v1: email)}
                            {--time-limit=3600 : Segundos máximos de ejecución}
                            {--limit= : Número máximo de mensajes}
                            {--sleep=1 : Segundos de espera si no hay mensajes}';

    protected $description = 'Consume mensajes de RabbitMQ (v1: cola email.send)';

    public function handle(MessengerFactory $messenger): int
    {
        $transport = (string) $this->argument('transport');
        $config = config("messenger.transports.{$transport}");

        if (! is_array($config)) {
            $this->error("Transporte [{$transport}] no configurado.");

            return self::FAILURE;
        }

        if (! ($config['consume'] ?? false)) {
            $this->error("El transporte [{$transport}] no se consume en v1. Las colas quedan declaradas para los productores.");

            return self::FAILURE;
        }

        $messenger->setupTopology();

        $limit = filled($this->option('limit')) ? (int) $this->option('limit') : null;
        $worker = $messenger->worker($transport, $limit);
        $this->trapSignals($worker);

        $this->info("Consumiendo [{$transport}] (cola {$config['queue']}).");

        $worker->run([
            'sleep' => (int) ((float) $this->option('sleep') * 1_000_000),
            'time_limit' => (int) $this->option('time-limit') ?: null,
            'fetch_size' => 1,
        ]);

        return self::SUCCESS;
    }

    private function trapSignals(Worker $worker): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function () use ($worker): void {
            $this->info('Deteniendo worker...');
            $worker->stop();
        };

        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
}
