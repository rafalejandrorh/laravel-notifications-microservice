<?php

return [

    'sivacrim-login-code' => [
        'latest' => 1,
        'from_identity' => 'notificaciones',
        'versions' => [
            1 => [
                'subject' => 'Validacion de Inicio de Sesión | SIVACRIM',
                'required_params' => ['primer_nombre', 'code'],
            ],
        ],
    ],

    'sivacrim-email-validation' => [
        'latest' => 1,
        'from_identity' => 'notificaciones',
        'versions' => [
            1 => [
                'subject' => 'Validacion de Correo | SIVACRIM',
                'required_params' => ['code'],
            ],
        ],
    ],

    'sivacrim-password-reset' => [
        'latest' => 1,
        'from_identity' => 'notificaciones',
        'versions' => [
            1 => [
                'subject' => 'Solicitud de Reestablecimiento de Contraseña',
                'required_params' => ['reset_url', 'expire_minutes'],
            ],
        ],
    ],

];
