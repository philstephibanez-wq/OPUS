<?php
declare(strict_types=1);

/**
 * OWASYS navigation ACL.
 *
 * The FSM remains the navigation authority. This ACL only authorizes events
 * for authenticated runtime profiles; it never invents states or routes.
 */
return [
    'admin' => ['*'],
    'dev' => [
        'open_home',
        'open_registry',
        'open_structure',
        'open_data',
        'open_workflows',
        'open_security',
        'open_source',
        'open_build',
        'change_app',
        'clear_app_context',
        'create_new_app',
        'logout',
    ],
    'viewer' => [
        'open_home',
        'open_registry',
        'change_app',
        'logout',
    ],
];
