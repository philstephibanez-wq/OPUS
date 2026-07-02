<?php
declare(strict_types=1);

// environment.prod.example.php

return [
    'environment' => 'prod',
    'display_errors' => false,
    'require_auth' => true,
    'auth' => [
        'mode' => 'local',
        'users' => [
            'admin' => [
                'password_hash' => 'CHANGE_ME_WITH_PASSWORD_HASH',
                'roles' => ['ops.admin'],
            ],
        ],
    ],
    'sso' => [
        'enabled' => false,
        'provider' => null,
    ],
    'delivery' => [
        'profile' => 'prod',
        'allow_demo_credentials' => false,
    ],
];
