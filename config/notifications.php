<?php

return [

    'api_key' => env('NOTIFICATIONS_API_KEY', env('EMAIL_API_KEY', '')),

    'claim_ttl_seconds' => (int) env('NOTIFICATION_CLAIM_TTL', env('EMAIL_CLAIM_TTL', 300)),

    'max_send_attempts' => (int) env('NOTIFICATION_MAX_SEND_ATTEMPTS', env('EMAIL_MAX_SEND_ATTEMPTS', 5)),

    'queue_driver' => env('NOTIFICATION_QUEUE_DRIVER', 'rabbitmq'),

    'queue' => [
        'retry' => [
            'delay_seconds' => (int) env('NOTIFICATION_QUEUE_RETRY_DELAY', 1),
            'multiplier' => (float) env('NOTIFICATION_QUEUE_RETRY_MULTIPLIER', 2),
            'max_delay_seconds' => (int) env('NOTIFICATION_QUEUE_RETRY_MAX_DELAY', 60),
        ],
    ],

];
