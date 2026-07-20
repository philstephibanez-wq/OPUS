<?php
declare(strict_types=1);

/**
 * OWASYS login view-model.
 * Runtime credentials only: username/password are stored in an ignored local user store.
 */
return [
    'title' => 'Login',
    'badge' => 'Password session',
    'summary' => 'Sign in with a local runtime OWASYS user account.',
    'sections' => [
        'Runtime local user store',
        'Password hash verification',
        'Production authentication pending',
    ],
    'cards' => [
        [
            'title' => 'Runtime credentials',
            'body' => 'OWASYS asks for a username and password, but the user store remains runtime-only and ignored by Git.',
            'items' => [
                'store: var/auth/local-users.json',
                'contract: OWASYS_LOCAL_USER_STORE_V1',
                'committed passwords: forbidden',
            ],
        ],
        [
            'title' => 'Next security step',
            'body' => 'RBAC enforcement, CSRF tokens and audit trail will be connected before write/export actions become web-executable.',
            'items' => [
                'protect create/write/export',
                'persist audit events',
                'replace local runtime store with configured identity provider',
            ],
        ],
    ],
    'contracts' => [
        'OWASYS_SECURITY_POLICY_V1',
        'OWASYS_LOCAL_USER_STORE_V1',
        'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    ],
    'actions' => [
        'Bootstrap a local OWASYS user',
        'Sign in with username and password',
        'Return to the OWASYS dashboard',
    ],
    'auth' => [
        'mode' => 'runtime-password-store',
        'profile' => 'admin-or-dev',
        'user_store' => 'var/auth/local-users.json',
        'committed_passwords_allowed' => false,
    ],
];
