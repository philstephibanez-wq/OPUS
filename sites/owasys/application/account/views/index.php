<?php
declare(strict_types=1);

/**
 * OWASYS account view-model.
 * Runtime password management for the authenticated local user.
 */
return [
    'title' => 'Account password',
    'badge' => 'Account',
    'summary' => 'Change the runtime OWASYS password for the current authenticated user.',
    'sections' => [
        'Current password verification',
        'Minimum password length',
        'Runtime store update',
    ],
    'cards' => [
        [
            'title' => 'Bootstrap password rotation',
            'body' => 'Initial bootstrap users are marked as requiring a password change before accessing OWASYS.',
            'items' => [
                'must_change_password flag',
                'password hash rewritten locally',
                'no committed password or hash',
            ],
        ],
        [
            'title' => 'Runtime-only credential store',
            'body' => 'The password change updates sites/owasys/var/auth/local-users.json, which is ignored by Git.',
            'items' => [
                'OWASYS_LOCAL_USER_STORE_V1',
                'PASSWORD_DEFAULT hash',
                'local state only',
            ],
        ],
    ],
    'contracts' => [
        'OWASYS_LOCAL_USER_STORE_V1',
        'OWASYS_SECURITY_POLICY_V1',
        'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    ],
    'actions' => [
        'Enter current password',
        'Choose a new password of at least 12 characters',
        'Return to dashboard after successful password change',
    ],
];
