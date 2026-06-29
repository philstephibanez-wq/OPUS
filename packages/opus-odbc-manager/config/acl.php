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
        'opus.odbc_manager.crud' => ['opus_admin', 'opus_developer'],
        'opus.odbc_manager.insert' => ['opus_admin', 'opus_developer'],
        'opus.odbc_manager.update' => ['opus_admin', 'opus_developer'],
        'opus.odbc_manager.delete' => ['opus_admin', 'opus_developer'],
        'opus.odbc_manager.crud_dry_run' => ['opus_admin', 'opus_developer'],
    ],
    'crud_policy' => [
        'raw_sql_allowed' => false,
        'ddl_allowed' => false,
        'dry_run_required_before_execute' => true,
        'confirmation_required' => true,
        'acl_required' => true,
    ],
    'disabled_until_guarded_milestone' => [
        'opus.odbc_manager.ddl',
        'opus.odbc_manager.sql_console',
    ],
];
