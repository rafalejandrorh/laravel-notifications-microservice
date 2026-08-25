<?php

return [

    'email' => [
        'welcome' => [
            'latest' => 1,
            'from_identity' => 'noreply',
            'versions' => [
                1 => [
                    'subject' => 'Bienvenido, {name}',
                    'required_params' => ['name'],
                ],
            ],
        ],
        'password-reset' => [
            'latest' => 1,
            'from_identity' => 'noreply',
            'versions' => [
                1 => [
                    'subject' => 'Restablecer contraseña',
                    'required_params' => ['reset_url'],
                ],
            ],
        ],
    ],

    'push' => [],

    'sms' => [],

];
