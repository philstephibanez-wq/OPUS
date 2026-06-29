<?php
declare(strict_types=1);

use OpusOdbcManager\Controller\CrudController;
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
    'opus_odbc_manager_crud' => [
        'path' => '/opus-odbc-manager/crud',
        'controller' => CrudController::class . '::overview',
        'template' => 'crud.score',
        'methods' => ['GET'],
        'permission' => 'opus.odbc_manager.crud',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'crud_overview'],
    ],
    'opus_odbc_manager_crud_insert' => [
        'path' => '/opus-odbc-manager/tables/{table}/crud/insert',
        'controller' => CrudController::class . '::insertForm',
        'template' => 'crud-form.score',
        'methods' => ['GET'],
        'permission' => 'opus.odbc_manager.insert',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'crud_insert_form'],
    ],
    'opus_odbc_manager_crud_update' => [
        'path' => '/opus-odbc-manager/tables/{table}/crud/update',
        'controller' => CrudController::class . '::updateForm',
        'template' => 'crud-form.score',
        'methods' => ['GET'],
        'permission' => 'opus.odbc_manager.update',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'crud_update_form'],
    ],
    'opus_odbc_manager_crud_delete' => [
        'path' => '/opus-odbc-manager/tables/{table}/crud/delete',
        'controller' => CrudController::class . '::deleteForm',
        'template' => 'crud-form.score',
        'methods' => ['GET'],
        'permission' => 'opus.odbc_manager.delete',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'crud_delete_form'],
    ],
    'opus_odbc_manager_crud_dry_run' => [
        'path' => '/opus-odbc-manager/tables/{table}/crud/{action}/dry-run',
        'controller' => CrudController::class . '::dryRun',
        'template' => 'crud-dry-run.score',
        'methods' => ['POST'],
        'permission' => 'opus.odbc_manager.crud_dry_run',
        'profiler' => ['category' => 'opus.odbc_manager', 'action' => 'crud_dry_run'],
    ],
];
