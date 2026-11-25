<?php

namespace App\Http\Controllers;

use App\Enums\QueueDriver;
use App\Messenger\MessengerFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $mongo = $this->mongoOk();
        $checks = [
            'app' => true,
            'mongodb' => $mongo,
        ];

        if (QueueDriver::current()->isRabbitMq()) {
            $checks['rabbitmq'] = $this->rabbitOk();
        } else {
            $checks['queue'] = $this->queueOk();
        }

        $ok = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }

    private function mongoOk(): bool
    {
        try {
            DB::connection('mongodb')->getMongoClient()->selectDatabase('admin')->command(['ping' => 1]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function rabbitOk(): bool
    {
        try {
            app(MessengerFactory::class)->transport('email')->getMessageCount();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function queueOk(): bool
    {
        try {
            $connection = (string) config('queue.default');

            if (in_array($connection, ['sync', 'null'], true)) {
                return true;
            }

            Queue::connection($connection)->size();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
