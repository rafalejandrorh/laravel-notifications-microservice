<?php

namespace Tests\Concerns;

use App\Models\InboxEvent;
use App\Repositories\InboxEventRepository;
use Illuminate\Support\Facades\DB;
use Throwable;

trait InteractsWithMongoInbox
{
    protected function setUpMongoInbox(): void
    {
        try {
            DB::connection('mongodb')->getMongoClient()->selectDatabase('admin')->command(['ping' => 1]);
        } catch (Throwable $exception) {
            $this->markTestSkipped('MongoDB no disponible: '.$exception->getMessage());
        }

        InboxEvent::truncate();
        $this->app->make(InboxEventRepository::class)->ensureIndexes();
    }
}
