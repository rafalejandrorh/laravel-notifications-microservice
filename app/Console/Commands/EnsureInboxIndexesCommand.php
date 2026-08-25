<?php

namespace App\Console\Commands;

use App\Repositories\InboxEventRepository;
use Illuminate\Console\Command;

class EnsureInboxIndexesCommand extends Command
{
    protected $signature = 'inbox:ensure-indexes';

    protected $description = 'Crea los índices únicos del inbox (event_id y channel+idempotency_key)';

    public function handle(InboxEventRepository $inbox): int
    {
        $inbox->ensureIndexes();

        $this->info('Índices del inbox verificados.');

        return self::SUCCESS;
    }
}
