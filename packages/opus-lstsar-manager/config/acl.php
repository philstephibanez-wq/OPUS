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
        'opus.lstsar_manager.declare' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.source' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.destination' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.mapping' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.rules' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.archive_report' => ['opus_admin', 'opus_developer'],
        'opus.lstsar_manager.dry_run' => ['opus_admin', 'opus_developer'],
    ],
    'lstsar_policy' => [
        'raw_sql_allowed' => false,
        'ddl_allowed' => false,
        'execute_allowed' => false,
        'dry_run_required_before_execute' => true,
        'acl_required' => true,
        'source_destination_models_required' => true,
        'mapping_required' => true,
    ],
    'disabled_until_guarded_milestone' => [
        'opus.lstsar_manager.execute',
        'opus.lstsar_manager.schedule',
        'opus.lstsar_manager.raw_sql',
        'opus.lstsar_manager.ddl',
    ],
];
