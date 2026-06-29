<?php
declare(strict_types=1);

return [
    'application' => 'opus-odbc-manager',
    'protected' => true,
    'anonymous' => false,
    'denied_by_default' => true,
    'roles' => [
        'opus_admin',
        'opus_developer',
    ],
    'permissions' => [
        'opus.odbc_manager.access' => ['opus_admin', 'opus_developer'],
        'opus.odbc_manager.read' => ['opus_admin', 'opus_developer'],
        'opus.odbc_manager.preview' => ['opus_admin', 'opus_developer'],
        'opus.odbc_manager.lstsar_draft' => ['opus_admin', 'opus_developer'],
    ],
    'disabled_until_guarded_milestone' => [
        'opus.odbc_manager.insert',
        'opus.odbc_manager.update',
        'opus.odbc_manager.delete',
        'opus.odbc_manager.ddl',
        'opus.odbc_manager.sql_console',
    ],
];
