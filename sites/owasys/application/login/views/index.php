<?php
declare(strict_types=1);

/**
 * OWASYS login view-model.
 * Foundation only: starts an explicit local dev session, without committed passwords.
 */
return [
    'title' => 'Login',
    'badge' => 'Local session',
    'summary' => 'Start an explicit local development session for OWASYS.',
    'sections' => [
        'Local development bootstrap',
        'Production authentication pending',
    ],
    'cards' => [
        [
            'title' => 'Session foundation',
            'body' => 'This page establishes the OWASYS session and UI state without pretending to be final production security.',
            'items' => [
                'profile: dev',
                'session: explicit',
                'committed passwords: forbidden',
            ],
        ],
        [
            'title' => 'Next security step',
            'body' => 'Admin authentication, RBAC enforcement, CSRF tokens and audit trail will be connected before write/export actions become web-executable.',
            'items' => [
                'protect create/write/export',
                'persist audit events',
                'replace local bootstrap with configured identity provider',
            ],
        ],
    ],
    'contracts' => [
        'OWASYS_SECURITY_POLICY_V1',
        'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    ],
    'actions' => [
        'Start a local dev session',
        'Return to the OWASYS dashboard',
    ],
    'auth' => [
        'mode' => 'local-dev-bootstrap',
        'profile' => 'dev',
        'committed_passwords_allowed' => false,
    ],
];
