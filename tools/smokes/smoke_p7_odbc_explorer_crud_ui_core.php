<?php
declare(strict_types=1);

use Opus\OdbcExplorer\Crud\OdbcCrudAction;
use OpusOdbcManager\Controller\CrudController;
use OpusOdbcManager\Diagnostics\OdbcManagerProfiler;
use OpusOdbcManager\View\OdbcManagerCrudViewModelFactory;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$packageRoot = $root . '/packages/opus-odbc-manager';
require_once $packageRoot . '/src/Diagnostics/OdbcManagerProfiler.php';
require_once $packageRoot . '/src/View/OdbcManagerCrudViewModelFactory.php';
require_once $packageRoot . '/src/Controller/CrudController.php';

echo "P7_ODBC_EXPLORER_CRUD_UI_CORE_SMOKE\n";

$manifest = json_decode((string) file_get_contents($packageRoot . '/opus.application.json'), true);
if (!is_array($manifest) || ($manifest['metadata']['crud_ui_core'] ?? false) !== true || ($manifest['metadata']['crud_enabled'] ?? false) !== true || ($manifest['metadata']['ddl_enabled'] ?? true) !== false) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_MANIFEST=FAIL');
}
echo "CHECK_ODBC_CRUD_UI_MANIFEST=OK\n";

$routes = require $packageRoot . '/app/routes.php';
$requiredRoutes = [
    'opus_odbc_manager_crud' => ['template' => 'crud.score', 'methods' => ['GET'], 'permission' => 'opus.odbc_manager.crud'],
    'opus_odbc_manager_crud_insert' => ['template' => 'crud-form.score', 'methods' => ['GET'], 'permission' => 'opus.odbc_manager.insert'],
    'opus_odbc_manager_crud_update' => ['template' => 'crud-form.score', 'methods' => ['GET'], 'permission' => 'opus.odbc_manager.update'],
    'opus_odbc_manager_crud_delete' => ['template' => 'crud-form.score', 'methods' => ['GET'], 'permission' => 'opus.odbc_manager.delete'],
    'opus_odbc_manager_crud_dry_run' => ['template' => 'crud-dry-run.score', 'methods' => ['POST'], 'permission' => 'opus.odbc_manager.crud_dry_run'],
];
foreach ($requiredRoutes as $name => $expected) {
    if (!isset($routes[$name])) {
        throw new RuntimeException('CHECK_ODBC_CRUD_UI_ROUTE_MISSING=FAIL:' . $name);
    }
    if (($routes[$name]['template'] ?? '') !== $expected['template'] || ($routes[$name]['methods'] ?? []) !== $expected['methods'] || ($routes[$name]['permission'] ?? '') !== $expected['permission']) {
        throw new RuntimeException('CHECK_ODBC_CRUD_UI_ROUTE_CONTRACT=FAIL:' . $name);
    }
    if (!is_file($packageRoot . '/templates/' . $expected['template'])) {
        throw new RuntimeException('CHECK_ODBC_CRUD_UI_TEMPLATE_MISSING=FAIL:' . $expected['template']);
    }
    $profiler = $routes[$name]['profiler'] ?? [];
    if (($profiler['category'] ?? '') !== 'opus.odbc_manager' || !str_starts_with((string) ($profiler['action'] ?? ''), 'crud_')) {
        throw new RuntimeException('CHECK_ODBC_CRUD_UI_ROUTE_PROFILER=FAIL:' . $name);
    }
}
echo "CHECK_ODBC_CRUD_UI_ROUTES=OK\n";
echo "CHECK_ODBC_CRUD_UI_TEMPLATES=OK\n";
echo "CHECK_ODBC_CRUD_UI_ROUTE_PROFILER=OK\n";

$acl = require $packageRoot . '/config/acl.php';
foreach (['opus.odbc_manager.crud', 'opus.odbc_manager.insert', 'opus.odbc_manager.update', 'opus.odbc_manager.delete', 'opus.odbc_manager.crud_dry_run'] as $permission) {
    if (!isset($acl['permissions'][$permission])) {
        throw new RuntimeException('CHECK_ODBC_CRUD_UI_ACL_PERMISSION=FAIL:' . $permission);
    }
}
if (($acl['crud_policy']['raw_sql_allowed'] ?? true) !== false || ($acl['crud_policy']['ddl_allowed'] ?? true) !== false || ($acl['crud_policy']['dry_run_required_before_execute'] ?? false) !== true) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_ACL_POLICY=FAIL');
}
echo "CHECK_ODBC_CRUD_UI_ACL=OK\n";

$navigation = require $packageRoot . '/config/navigation.php';
$hasCrud = false;
foreach ($navigation['items'] ?? [] as $item) {
    if (($item['route'] ?? '') === 'opus_odbc_manager_crud') {
        $hasCrud = true;
    }
}
if (!$hasCrud) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_NAVIGATION=FAIL');
}
echo "CHECK_ODBC_CRUD_UI_NAVIGATION=OK\n";

$profilerConfig = require $packageRoot . '/config/profiler.php';
foreach (['crud_overview', 'crud_insert_form', 'crud_update_form', 'crud_delete_form', 'crud_dry_run'] as $action) {
    if (!in_array($action, $profilerConfig['actions'] ?? [], true)) {
        throw new RuntimeException('CHECK_ODBC_CRUD_UI_PROFILER_ACTION=FAIL:' . $action);
    }
}
echo "CHECK_ODBC_CRUD_UI_PROFILER_CONFIG=OK\n";

$fr = require $packageRoot . '/i18n/fr.php';
$en = require $packageRoot . '/i18n/en.php';
foreach (['odbc_manager.crud.enabled', 'odbc_manager.crud.insert', 'odbc_manager.crud.update', 'odbc_manager.crud.delete', 'odbc_manager.crud.dry_run'] as $key) {
    if (!isset($fr[$key], $en[$key])) {
        throw new RuntimeException('CHECK_ODBC_CRUD_UI_I18N=FAIL:' . $key);
    }
}
echo "CHECK_ODBC_CRUD_UI_I18N=OK\n";

$factory = new OdbcManagerCrudViewModelFactory();
$overview = $factory->overview();
if (($overview['mode'] ?? '') !== 'guarded_crud' || ($overview['raw_sql_allowed'] ?? true) !== false || ($overview['ddl_allowed'] ?? true) !== false || count($overview['actions'] ?? []) !== 3) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_OVERVIEW_VM=FAIL');
}
echo "CHECK_ODBC_CRUD_UI_OVERVIEW_VM=OK\n";

foreach (OdbcCrudAction::all() as $action) {
    $form = $factory->form($action, 'users');
    if (($form['action'] ?? '') !== $action || ($form['table'] ?? '') !== 'users' || ($form['raw_sql_allowed'] ?? true) !== false) {
        throw new RuntimeException('CHECK_ODBC_CRUD_UI_FORM_VM=FAIL:' . $action);
    }
    if (OdbcCrudAction::isDestructive($action) && ($form['predicate_required'] ?? false) !== true) {
        throw new RuntimeException('CHECK_ODBC_CRUD_UI_FORM_PREDICATE=FAIL:' . $action);
    }
}
echo "CHECK_ODBC_CRUD_UI_FORM_VM=OK\n";

$controller = new CrudController($factory);
if (($controller->overview()['mode'] ?? '') !== 'guarded_crud') {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_CONTROLLER_OVERVIEW=FAIL');
}
if (($controller->insertForm('users')['action'] ?? '') !== 'insert') {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_INSERT_FORM=FAIL');
}
if (($controller->updateForm('users')['predicate_required'] ?? false) !== true) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_UPDATE_FORM=FAIL');
}
if (($controller->deleteForm('users')['predicate_required'] ?? false) !== true) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_DELETE_FORM=FAIL');
}
echo "CHECK_ODBC_CRUD_UI_CONTROLLERS=OK\n";

$insertDryRun = $controller->dryRun('insert', 'users', ['name' => 'Alice'], []);
if (($insertDryRun['result']['action'] ?? '') !== 'insert' || ($insertDryRun['result']['dry_run'] ?? false) !== true || ($insertDryRun['result']['audit']['sql_plan']['audit']['raw_sql_allowed'] ?? true) !== false) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_INSERT_DRY_RUN=FAIL');
}
echo "CHECK_ODBC_CRUD_UI_INSERT_DRY_RUN=OK\n";

$updateDryRun = $controller->dryRun('update', 'users', ['name' => 'Bob'], ['id' => 1]);
if (($updateDryRun['result']['action'] ?? '') !== 'update' || ($updateDryRun['result']['dry_run'] ?? false) !== true) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_UPDATE_DRY_RUN=FAIL');
}
echo "CHECK_ODBC_CRUD_UI_UPDATE_DRY_RUN=OK\n";

$deleteDryRun = $controller->dryRun('delete', 'users', [], ['id' => 1]);
if (($deleteDryRun['result']['action'] ?? '') !== 'delete' || ($deleteDryRun['result']['dry_run'] ?? false) !== true) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UI_DELETE_DRY_RUN=FAIL');
}
echo "CHECK_ODBC_CRUD_UI_DELETE_DRY_RUN=OK\n";


foreach ($routes as $route) {
    $path = strtolower((string) ($route['path'] ?? ''));
    foreach (['drop', 'alter', 'create', 'sql'] as $forbidden) {
        if (str_contains($path, $forbidden)) {
            throw new RuntimeException('CHECK_ODBC_CRUD_UI_NO_DDL_SQL_ROUTES=FAIL');
        }
    }
}
echo "CHECK_ODBC_CRUD_UI_NO_DDL_SQL_ROUTES=OK\n";

echo "P7_ODBC_EXPLORER_CRUD_UI_CORE_SMOKE_OK\n";
