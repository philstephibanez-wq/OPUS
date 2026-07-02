<?php
declare(strict_types=1);

return [
    'environment' => 'dev',
    'display_errors' => true,
    'require_auth' => true,
    'auth' => [
        'mode' => 'local',
        'users' => [
            'admin' => [
                'password_hash' => '$2y$12$9Yty46R40vwiQJ9OjvH5uuMHD/hmDgMHBLdrC/Jo0ouVFZYEnJcVK',
                'roles' => ['ops.admin'],
            ],
        ],
    ],
    'sso' => [
        'enabled' => false,
        'provider' => null,
    ],
    'delivery' => [
        'profile' => 'dev',
        'allow_demo_credentials' => true,
    ],
];
