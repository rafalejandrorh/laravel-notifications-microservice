<?php

namespace App\Http\Controllers;

use App\Channels\Email\TemplateCatalog;
use App\Enums\NotificationChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function __construct(
        private TemplateCatalog $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $channelValue = $request->query('channel', NotificationChannel::Email->value);
        $channel = NotificationChannel::tryFrom((string) $channelValue);

        if ($channel === null) {
            return response()->json(['message' => 'Canal inválido.'], 422);
        }

        return response()->json([
            'channel' => $channel->value,
            'templates' => $this->catalog->list($channel),
        ]);
    }
}
