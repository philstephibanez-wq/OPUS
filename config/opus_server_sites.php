<?php

declare(strict_types=1);

/*
 * OPUS server site registry.
 *
 * This file declares the sites supervised by the native OPUS server control
 * plane for the local UwAmp/Apache server. It is runtime configuration, not a
 * handoff document.
 */
$sharedPublicRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';

return [
    [
        'id' => 'opus-admin',
        'label' => 'OPUS Administration',
        'host' => 'opus.localhost',
        'public_root' => $sharedPublicRoot,
        'expected_fsm_state' => 'SITE_READY',
        'auth_profile' => 'native_admin_sso_pending',
        'acl_profile' => 'server_admin_readonly',
    ],
    [
        'id' => 'logandplay',
        'label' => 'LogAndPlay public site',
        'host' => 'logandplay.localhost',
        'public_root' => $sharedPublicRoot,
        'expected_fsm_state' => 'SITE_READY',
        'auth_profile' => 'public_site',
        'acl_profile' => 'public_readonly',
    ],
    [
        'id' => 'demo',
        'label' => 'ASAP / OPUS demo site',
        'host' => 'demo.logandplay.localhost',
        'public_root' => $sharedPublicRoot,
        'expected_fsm_state' => 'SITE_READY',
        'auth_profile' => 'demo_public',
        'acl_profile' => 'demo_readonly',
    ],
    [
        'id' => 'maestro',
        'label' => 'Maestro documentation site',
        'host' => 'maestro.logandplay.localhost',
        'public_root' => $sharedPublicRoot,
        'expected_fsm_state' => 'SITE_READY',
        'auth_profile' => 'documentation_public',
        'acl_profile' => 'documentation_readonly',
    ],
];