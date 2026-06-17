<?php

declare(strict_types=1);

/*
 * OPUS server site registry.
 *
 * Runtime configuration for the local OPUS/UwAmp multi-site server control
 * plane. This is not a handoff document and must not infer hidden sites from
 * Apache. Missing roots are supervised as BLOCKED by the dashboard.
 */
$opusEngineRoot = dirname(__DIR__);
$uwampWwwRoot = 'H:\\UwAmp\\www';
$logAndPlayRoot = $uwampWwwRoot . DIRECTORY_SEPARATOR . 'LogAndPlay.org';

return [
    [
        'id' => 'opus-admin',
        'label' => 'OPUS Administration',
        'host' => 'opus.localhost',
        'site_type' => 'server_admin',
        'engine_root' => $opusEngineRoot,
        'site_root' => $opusEngineRoot,
        'public_root' => $opusEngineRoot . DIRECTORY_SEPARATOR . 'public',
        'expected_fsm_state' => 'SITE_READY',
        'auth_profile' => 'native_admin_sso_pending',
        'acl_profile' => 'server_admin_readonly',
        'routes_profile' => 'admin_control_plane',
        'api_profile' => 'admin_api_sso_pending',
        'enabled' => true,
    ],
    [
        'id' => 'logandplay',
        'label' => 'LogAndPlay public site',
        'host' => 'logandplay.localhost',
        'site_type' => 'public_site',
        'engine_root' => $opusEngineRoot,
        'site_root' => $logAndPlayRoot,
        'public_root' => $logAndPlayRoot,
        'expected_fsm_state' => 'SITE_READY',
        'auth_profile' => 'public_site',
        'acl_profile' => 'public_readonly',
        'routes_profile' => 'public_pages',
        'api_profile' => 'public_api_none',
        'enabled' => true,
    ],
    [
        'id' => 'demo',
        'label' => 'ASAP / OPUS demo site',
        'host' => 'demo.logandplay.localhost',
        'site_type' => 'demo_site',
        'engine_root' => $opusEngineRoot,
        'site_root' => $logAndPlayRoot . DIRECTORY_SEPARATOR . 'demo',
        'public_root' => $logAndPlayRoot . DIRECTORY_SEPARATOR . 'demo',
        'expected_fsm_state' => 'SITE_READY',
        'auth_profile' => 'demo_public',
        'acl_profile' => 'demo_readonly',
        'routes_profile' => 'demo_pages',
        'api_profile' => 'demo_api_readonly',
        'enabled' => true,
    ],
    [
        'id' => 'maestro',
        'label' => 'Maestro documentation site',
        'host' => 'maestro.logandplay.localhost',
        'site_type' => 'documentation_site',
        'engine_root' => $opusEngineRoot,
        'site_root' => $logAndPlayRoot . DIRECTORY_SEPARATOR . 'maestro',
        'public_root' => $logAndPlayRoot . DIRECTORY_SEPARATOR . 'maestro',
        'expected_fsm_state' => 'SITE_READY',
        'auth_profile' => 'documentation_public',
        'acl_profile' => 'documentation_readonly',
        'routes_profile' => 'documentation_pages',
        'api_profile' => 'documentation_api_readonly',
        'enabled' => true,
    ],
];
