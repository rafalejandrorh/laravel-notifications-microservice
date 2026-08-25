<?php

return [

    'api_key' => env('NOTIFICATIONS_API_KEY', env('EMAIL_API_KEY', '')),

    'claim_ttl_seconds' => (int) env('NOTIFICATION_CLAIM_TTL', env('EMAIL_CLAIM_TTL', 300)),

    'max_send_attempts' => (int) env('NOTIFICATION_MAX_SEND_ATTEMPTS', env('EMAIL_MAX_SEND_ATTEMPTS', 5)),

];
