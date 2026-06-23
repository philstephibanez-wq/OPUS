<?php

/**
 * OPUS boot FSM program.
 *
 * The FSM is the processor. This file is the boot instruction program.
 * No Boot class is allowed to hard-code this flow.
 */
return array(
    'initial_state' => 'BOOT_RESET',
    'ready_state' => 'BOOT_READY',
    'nmi_state' => 'BOOT_NMI',
    'memory' => array(
        'program' => 'opus.boot',
        'contract' => 'FSM_FIRST',
    ),
    'boot_sequence' => array(
        'BOOT_BEGIN',
        'CONFIG_LOADED',
        'SITE_RESOLVED',
        'RUNTIME_INITIALIZED',
        'BOOT_COMPLETE',
    ),
    'transitions' => array(
        array(
            'from' => 'BOOT_RESET',
            'signal' => 'BOOT_BEGIN',
            'to' => 'BOOT_LOADING_CONFIG',
            'action' => 'BOOT_BEGIN',
        ),
        array(
            'from' => 'BOOT_LOADING_CONFIG',
            'signal' => 'CONFIG_LOADED',
            'to' => 'BOOT_RESOLVING_SITE',
            'action' => 'CONFIG_LOADED',
        ),
        array(
            'from' => 'BOOT_RESOLVING_SITE',
            'signal' => 'SITE_RESOLVED',
            'to' => 'BOOT_INITIALIZING_RUNTIME',
            'action' => 'SITE_RESOLVED',
        ),
        array(
            'from' => 'BOOT_INITIALIZING_RUNTIME',
            'signal' => 'RUNTIME_INITIALIZED',
            'to' => 'BOOT_FINALIZING',
            'action' => 'RUNTIME_INITIALIZED',
        ),
        array(
            'from' => 'BOOT_FINALIZING',
            'signal' => 'BOOT_COMPLETE',
            'to' => 'BOOT_READY',
            'action' => 'BOOT_COMPLETE',
        ),
    ),
);
