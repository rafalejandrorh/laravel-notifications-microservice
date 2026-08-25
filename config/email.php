<?php

return [

    'api_key' => env('EMAIL_API_KEY', ''),

    'claim_ttl_seconds' => (int) env('EMAIL_CLAIM_TTL', 300),

    'max_send_attempts' => (int) env('EMAIL_MAX_SEND_ATTEMPTS', 5),

    'failover_mailer' => env('MAIL_FAILOVER_MAILER'),

    'from_identities' => [
        'noreply' => [
            'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
            'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Email')),
        ],
        'notificaciones' => [
            'address' => env('MAIL_NOTIFICATIONS_ADDRESS', env('MAIL_FROM_ADDRESS', 'noreply@example.com')),
            'name' => env('MAIL_NOTIFICATIONS_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Email'))),
        ],
    ],

    'gmail' => [
        'service_account_json' => env('GMAIL_SERVICE_ACCOUNT_JSON'),
        'delegated_user' => env('GMAIL_DELEGATED_USER'),
    ],

];
