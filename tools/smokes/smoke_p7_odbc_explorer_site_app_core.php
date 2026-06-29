<?php
declare(strict_types=1);

use Opus\Application\Package\ApplicationPackageManifest;
use OpusOdbcManager\Controller\CrudController;
use OpusOdbcManager\Controller\DashboardController;
use OpusOdbcManager\Controller\DataSourcesController;
use OpusOdbcManager\Controller\LstsarDraftController;
use OpusOdbcManager\Controller\PreviewController;
use OpusOdbcManager\Controller\TableController;
use OpusOdbcManager\Controller\TablesController;
use OpusOdbcManager\Diagnostics\OdbcManagerProfiler;
use OpusOdbcManager\OdbcManagerPackage;
use OpusOdbcManager\View\OdbcManagerReadOnlyViewModelFactory;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$packageRoot = $root . '/packages/opus-odbc-manager';
require_once $packageRoot . '/src/OdbcManagerPackage.php';
require_once $packageRoot . '/src/Diagnostics/OdbcManagerProfiler.php';
require_once $packageRoot . '/src/View/OdbcManagerReadOnlyViewModelFactory.php';
require_once $packageRoot . '/src/View/OdbcManagerCrudViewModelFactory.php';
require_once $packageRoot . '/src/Controller/DashboardController.php';
require_once $packageRoot . '/src/Controller/DataSourcesController.php';
require_once $packageRoot . '/src/Controller/TablesController.php';
require_once $packageRoot . '/src/Controller/TableController.php';
require_once $packageRoot . '/src/Controller/PreviewController.php';
require_once $packageRoot . '/src/Controller/LstsarDraftController.php';
require_once $packageRoot . '/src/Controller/CrudController.php';

final class OdbcManagerFakeProfiler
{
    /** @var list<array<string,mixed>> */
    public array $events = [];

    /** @param array<string,mixed> $context */
    public function event(string $category, string $name, array $context = []): void
    {
        $this->events[] = ['category' => $category, 'name' => $name, 'context' => $context];
    }
}

echo "P7_ODBC_EXPLORER_SITE_APP_CORE_SMOKE\n";

$manifest = ApplicationPackageManifest::fromFile($packageRoot . '/opus.application.json');
if ($manifest->packageName() !== 'logandplay/opus-odbc-manager' || $manifest->applicationSlug() !== 'opus-odbc-manager') {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_MANIFEST=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_MANIFEST=OK\n";

if (!$manifest->isProtected()) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_PROTECTED=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_PROTECTED=OK\n";

$manifestData = json_decode((string) file_get_contents($packageRoot . '/opus.application.json'), true);
if (!is_array($manifestData) || ($manifestData['metadata']['site_app_core'] ?? false) !== true) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_METADATA=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_METADATA=OK\n";

if (($manifestData['integrations']['profiler'] ?? false) !== true || ($manifestData['metadata']['profiler_instrumented'] ?? false) !== true || ($manifestData['paths']['profiler'] ?? '') !== 'config/profiler.php') {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_PROFILER_MANIFEST=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_PROFILER_MANIFEST=OK\n";

$composer = json_decode((string) file_get_contents($packageRoot . '/composer.json'), true);
if (!is_array($composer) || ($composer['type'] ?? '') !== 'opus-application' || !isset($composer['autoload']['psr-4']['OpusOdbcManager\\'])) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_COMPOSER=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_COMPOSER=OK\n";

if (!OdbcManagerPackage::isReadOnly()) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_PACKAGE_MODE=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_PACKAGE_MODE=OK\n";

$routes = require $packageRoot . '/app/routes.php';
if (!is_array($routes) || count($routes) < 6) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_ROUTES_COUNT=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_ROUTES_COUNT=OK\n";

$requiredRoutes = [
    'opus_odbc_manager_dashboard' => ['template' => 'dashboard.score', 'action' => 'dashboard', 'methods' => ['GET']],
    'opus_odbc_manager_datasources' => ['template' => 'datasources.score', 'action' => 'datasources', 'methods' => ['GET']],
    'opus_odbc_manager_tables' => ['template' => 'tables.score', 'action' => 'tables', 'methods' => ['GET']],
    'opus_odbc_manager_table_detail' => ['template' => 'table-detail.score', 'action' => 'table_detail', 'methods' => ['GET']],
    'opus_odbc_manager_table_preview' => ['template' => 'preview.score', 'action' => 'preview', 'methods' => ['GET']],
    'opus_odbc_manager_lstsar_draft' => ['template' => 'lstsar-draft.score', 'action' => 'lstsar_draft', 'methods' => ['GET']],
];
foreach ($requiredRoutes as $name => $expected) {
    $template = $expected['template'];
    if (($routes[$name]['template'] ?? '') !== $template || ($routes[$name]['methods'] ?? []) !== $expected['methods']) {
        throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_ROUTE_' . strtoupper($name) . '=FAIL');
    }
    if (!is_file($packageRoot . '/templates/' . $template)) {
        throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_TEMPLATE_' . strtoupper($template) . '=FAIL');
    }
    if (!str_starts_with((string) ($routes[$name]['permission'] ?? ''), 'opus.odbc_manager.')) {
        throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_PERMISSION_' . strtoupper($name) . '=FAIL');
    }
    $profiler = $routes[$name]['profiler'] ?? [];
    if (($profiler['category'] ?? '') !== 'opus.odbc_manager' || ($profiler['action'] ?? '') !== $expected['action']) {
        throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_ROUTE_PROFILER_' . strtoupper($name) . '=FAIL');
    }
}
echo "CHECK_ODBC_MANAGER_SITE_ROUTES=OK\n";
echo "CHECK_ODBC_MANAGER_SITE_TEMPLATES=OK\n";
echo "CHECK_ODBC_MANAGER_SITE_ROUTE_PROFILER=OK\n";

$profilerConfig = require $packageRoot . '/config/profiler.php';
if (!is_array($profilerConfig) || ($profilerConfig['contract'] ?? '') !== 'OPUS_ODBC_MANAGER_PROFILER_CONFIG_V1' || ($profilerConfig['category'] ?? '') !== 'opus.odbc_manager') {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_PROFILER_CONFIG=FAIL');
}
foreach (['dashboard', 'datasources', 'tables', 'table_detail', 'preview', 'lstsar_draft'] as $action) {
    if (!in_array($action, $profilerConfig['actions'] ?? [], true)) {
        throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_PROFILER_ACTION=FAIL');
    }
}
echo "CHECK_ODBC_MANAGER_SITE_PROFILER_CONFIG=OK\n";

$acl = require $packageRoot . '/config/acl.php';
if (!is_array($acl) || ($acl['protected'] ?? false) !== true || ($acl['anonymous'] ?? true) !== false || ($acl['denied_by_default'] ?? false) !== true) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_ACL=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_ACL=OK\n";

$navigation = require $packageRoot . '/config/navigation.php';
if (!is_array($navigation) || count($navigation['items'] ?? []) < 3) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_NAVIGATION=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_NAVIGATION=OK\n";

$fr = require $packageRoot . '/i18n/fr.php';
$en = require $packageRoot . '/i18n/en.php';
foreach (['odbc_manager.title', 'odbc_manager.dashboard', 'odbc_manager.tables', 'odbc_manager.crud.disabled'] as $key) {
    if (!array_key_exists($key, $fr) || !array_key_exists($key, $en)) {
        throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_I18N_KEY=FAIL');
    }
}
echo "CHECK_ODBC_MANAGER_SITE_I18N=OK\n";

$factory = new OdbcManagerReadOnlyViewModelFactory();
$dashboard = (new DashboardController($factory))->dashboard();
if (($dashboard['mode'] ?? '') !== 'readonly') {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_DASHBOARD_VM=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_DASHBOARD_VM=OK\n";

if (((new DataSourcesController($factory))->datasources()['mode'] ?? '') !== 'readonly') {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_DATASOURCES_VM=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_DATASOURCES_VM=OK\n";

if (((new TablesController($factory))->tables()['mode'] ?? '') !== 'readonly') {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_TABLES_VM=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_TABLES_VM=OK\n";

if (((new TableController($factory))->detail('users')['table'] ?? '') !== 'users') {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_TABLE_DETAIL_VM=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_TABLE_DETAIL_VM=OK\n";

$preview = (new PreviewController($factory))->preview('users', 999);
if (($preview['limit'] ?? 0) !== 200) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_PREVIEW_LIMIT=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_PREVIEW_LIMIT=OK\n";

$draft = (new LstsarDraftController($factory))->draft('users');
if (($draft['draft']['odbc_only'] ?? false) !== true) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_LSTSAR_VM=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_LSTSAR_VM=OK\n";

$fake = new OdbcManagerFakeProfiler();
$profiler = OdbcManagerProfiler::fromProfiler($fake);
$instrumentedDashboard = (new DashboardController($factory, $profiler))->dashboard();
if (($instrumentedDashboard['mode'] ?? '') !== 'readonly' || count($fake->events) !== 2) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_PROFILER_EVENTS=FAIL');
}
if (($fake->events[0]['category'] ?? '') !== 'opus.odbc_manager' || ($fake->events[0]['name'] ?? '') !== 'action.started' || ($fake->events[1]['name'] ?? '') !== 'action.finished') {
    throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_PROFILER_EVENT_NAMES=FAIL');
}
echo "CHECK_ODBC_MANAGER_SITE_PROFILER_EVENTS=OK\n";

foreach ($routes as $route) {
    $path = strtolower((string) ($route['path'] ?? ''));
    foreach (['drop', 'alter', 'create', 'sql'] as $forbidden) {
        if (str_contains($path, $forbidden)) {
            throw new RuntimeException('CHECK_ODBC_MANAGER_SITE_NO_DDL_SQL_ROUTES=FAIL');
        }
    }
}
echo "CHECK_ODBC_MANAGER_SITE_NO_DDL_SQL_ROUTES=OK\n";

echo "P7_ODBC_EXPLORER_SITE_APP_CORE_SMOKE_OK\n";
