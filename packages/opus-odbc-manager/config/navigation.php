<?php
declare(strict_types=1);

return [
    'application' => 'opus-odbc-manager',
    'items' => [
        ['label' => 'Dashboard', 'route' => 'opus_odbc_manager_dashboard', 'permission' => 'opus.odbc_manager.access'],
        ['label' => 'Data sources', 'route' => 'opus_odbc_manager_datasources', 'permission' => 'opus.odbc_manager.read'],
        ['label' => 'Tables', 'route' => 'opus_odbc_manager_tables', 'permission' => 'opus.odbc_manager.read'],
        ['label' => 'Guarded CRUD', 'route' => 'opus_odbc_manager_crud', 'permission' => 'opus.odbc_manager.crud'],
    ],
];
