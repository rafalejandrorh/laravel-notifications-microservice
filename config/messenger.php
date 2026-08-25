<?php

return [

    'dsn' => env('MESSENGER_TRANSPORT_DSN', 'amqp://guest:guest@127.0.0.1:5672/%2f'),

    'failure_dsn' => env('MESSENGER_FAILURE_DSN', env('MESSENGER_TRANSPORT_DSN', 'amqp://guest:guest@127.0.0.1:5672/%2f')),

    'exchange' => env('MESSENGER_EXCHANGE', 'notifications'),

    'retry' => [
        'max_retries' => (int) env('MESSENGER_MAX_RETRIES', 5),
        'delay_ms' => (int) env('MESSENGER_RETRY_DELAY_MS', 1000),
        'multiplier' => (float) env('MESSENGER_RETRY_MULTIPLIER', 2),
        'max_delay_ms' => (int) env('MESSENGER_RETRY_MAX_DELAY_MS', 60000),
    ],

    'transports' => [
        'email' => [
            'routing_key' => env('MESSENGER_ROUTING_KEY', 'email.send'),
            'queue' => env('MESSENGER_QUEUE', 'email.send'),
            'failure_queue' => env('MESSENGER_FAILURE_QUEUE', 'email.send.dlq'),
            'consume' => true,
        ],
        'push' => [
            'routing_key' => 'push.send',
            'queue' => 'push.send',
            'failure_queue' => 'push.send.dlq',
            'consume' => false,
        ],
        'sms' => [
            'routing_key' => 'sms.send',
            'queue' => 'sms.send',
            'failure_queue' => 'sms.send.dlq',
            'consume' => false,
        ],
    ],

];
