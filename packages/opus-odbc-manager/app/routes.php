<?php
declare(strict_types=1);

use OpusOdbcManager\Controller\DashboardController;
use OpusOdbcManager\Controller\DataSourcesController;
use OpusOdbcManager\Controller\LstsarDraftController;
use OpusOdbcManager\Controller\PreviewController;
use OpusOdbcManager\Controller\TableController;
use OpusOdbcManager\Controller\TablesController;

return [
    'opus_odbc_manager_dashboard' => [
        'path' => '/opus-odbc-manager',
        'controller' => DashboardController::class . '::dashboard',
        'template' => 'dashboard.score',
        'methods' => ['GET'],
        'permission' => 'opus.odbc_manager.access',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'dashboard'],
    ],
    'opus_odbc_manager_datasources' => [
        'path' => '/opus-odbc-manager/datasources',
        'controller' => DataSourcesController::class . '::datasources',
        'template' => 'datasources.score',
        'methods' => ['GET'],
        'permission' => 'opus.odbc_manager.read',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'datasources'],
    ],
    'opus_odbc_manager_tables' => [
        'path' => '/opus-odbc-manager/tables',
        'controller' => TablesController::class . '::tables',
        'template' => 'tables.score',
        'methods' => ['GET'],
        'permission' => 'opus.odbc_manager.read',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'tables'],
    ],
    'opus_odbc_manager_table_detail' => [
        'path' => '/opus-odbc-manager/tables/{table}',
        'controller' => TableController::class . '::detail',
        'template' => 'table-detail.score',
        'methods' => ['GET'],
        'permission' => 'opus.odbc_manager.read',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'table_detail'],
    ],
    'opus_odbc_manager_table_preview' => [
        'path' => '/opus-odbc-manager/tables/{table}/preview',
        'controller' => PreviewController::class . '::preview',
        'template' => 'preview.score',
        'methods' => ['GET'],
        'permission' => 'opus.odbc_manager.preview',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'preview'],
    ],
    'opus_odbc_manager_lstsar_draft' => [
        'path' => '/opus-odbc-manager/tables/{table}/lstsar-draft',
        'controller' => LstsarDraftController::class . '::draft',
        'template' => 'lstsar-draft.score',
        'methods' => ['GET'],
        'permission' => 'opus.odbc_manager.lstsar_draft',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'lstsar_draft'],
    ],
];
