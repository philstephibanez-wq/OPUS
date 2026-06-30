<?php
declare(strict_types=1);

return [
    'application' => 'opus-lstsar-manager',
    'items' => [
        ['label' => 'Dashboard', 'route' => 'opus_lstsar_manager_dashboard', 'permission' => 'opus.lstsar_manager.access'],
        ['label' => 'Declarations', 'route' => 'opus_lstsar_manager_declarations', 'permission' => 'opus.lstsar_manager.declare'],
        ['label' => 'Sources', 'route' => 'opus_lstsar_manager_sources', 'permission' => 'opus.lstsar_manager.source'],
        ['label' => 'Destinations', 'route' => 'opus_lstsar_manager_destinations', 'permission' => 'opus.lstsar_manager.destination'],
        ['label' => 'Mappings', 'route' => 'opus_lstsar_manager_mappings', 'permission' => 'opus.lstsar_manager.mapping'],
        ['label' => 'Rules', 'route' => 'opus_lstsar_manager_rules', 'permission' => 'opus.lstsar_manager.rules'],
        ['label' => 'Archive & Report', 'route' => 'opus_lstsar_manager_archive_report', 'permission' => 'opus.lstsar_manager.archive_report'],
        ['label' => 'Dry-run', 'route' => 'opus_lstsar_manager_dry_run', 'permission' => 'opus.lstsar_manager.dry_run'],
    ],
];
