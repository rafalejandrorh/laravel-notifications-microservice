<?php

namespace App\Http\Controllers;

use App\Messenger\MessengerFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(MessengerFactory $messenger): JsonResponse
    {
        $mongo = $this->mongoOk();
        $rabbit = $this->rabbitOk($messenger);
        $ok = $mongo && $rabbit;

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => [
                'app' => true,
                'mongodb' => $mongo,
                'rabbitmq' => $rabbit,
            ],
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

    private function rabbitOk(MessengerFactory $messenger): bool
    {
        try {
            $messenger->transport('email')->getMessageCount();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
