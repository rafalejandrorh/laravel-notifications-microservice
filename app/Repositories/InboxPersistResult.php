<?php

namespace App\Repositories;

use App\Models\InboxEvent;

final readonly class InboxPersistResult
{
    public function __construct(
        public InboxEvent $event,
        public bool $wasInserted,
    ) {}

    public function wasDuplicate(): bool
    {
        return ! $this->wasInserted;
    }
}
