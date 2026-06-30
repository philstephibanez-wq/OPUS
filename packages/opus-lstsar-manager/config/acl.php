<?php
declare(strict_types=1);

return [
    'application' => 'opus-lstsar-manager',
    'protected' => true,
    'anonymous' => false,
    'denied_by_default' => true,
    'roles' => [
        'opus_admin',
        'opus_developer',
    ],
    'permissions' => [
        'opus.lstsar_manager.access' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.operations' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.declare' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.source' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.destination' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.mapping' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.rules' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.archive_report' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.dry_run' => ['opus_admin', 'opus_developer'],
    ],
    'lstsar_policy' => [
        'odbc_only' => true,
        'model_driven' => true,
        'mapping_required' => true,
        'assignments_allowed' => true,
        'operations_dashboard_enabled' => true,
        'site_client_scoped_operations' => true,
        'dry_run_allowed' => true,
        'dry_run_engine_integration' => true,
        'execute_allowed' => false,
        'manual_launch_allowed' => false,
        'scheduler_launch_allowed' => false,
        'direct_execute_allowed' => false,
        'raw_sql_allowed' => false,
        'ddl_allowed' => false,
    ],
    'disabled_until_guarded_milestone' => [
        'opus.lstsar_manager.execute',
        'opus.lstsar_manager.manual_launch',
        'opus.lstsar_manager.scheduler_trigger',
        'opus.lstsar_manager.sql_console',
        'opus.lstsar_manager.ddl',
    ],
];
