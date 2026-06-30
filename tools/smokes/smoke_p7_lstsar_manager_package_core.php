<?php
declare(strict_types=1);

use OpusLstsarManager\Config\LstsarManagerDeclarationRepository;
use OpusLstsarManager\Controller\DashboardController;
use OpusLstsarManager\Controller\DeclarationsController;
use OpusLstsarManager\Controller\DryRunController;
use OpusLstsarManager\Diagnostics\LstsarManagerProfiler;
use OpusLstsarManager\DryRun\LstsarManagerDryRunService;
use OpusLstsarManager\View\LstsarManagerViewModelFactory;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$packageRoot = $root . '/packages/opus-lstsar-manager';
require_once $packageRoot . '/src/Diagnostics/LstsarManagerProfiler.php';
require_once $packageRoot . '/src/Config/LstsarManagerDeclarationRepository.php';
require_once $packageRoot . '/src/DryRun/LstsarManagerDryRunService.php';
require_once $packageRoot . '/src/View/LstsarManagerViewModelFactory.php';
require_once $packageRoot . '/src/Controller/DashboardController.php';
require_once $packageRoot . '/src/Controller/DeclarationsController.php';
require_once $packageRoot . '/src/Controller/DryRunController.php';
require_once $root . '/Opus/Lstsar/01_Load.php';
require_once $root . '/Opus/Lstsar/02_Secure.php';
require_once $root . '/Opus/Lstsar/03_Transform.php';
require_once $root . '/Opus/Lstsar/04_Store.php';
require_once $root . '/Opus/Lstsar/05_Archive.php';
require_once $root . '/Opus/Lstsar/06_Report.php';

echo "P7_LSTSAR_MANAGER_PACKAGE_CORE_SMOKE
";

$fail = static function (string $check, string $detail = ''): void {
    echo $check . '=FAIL' . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
    exit(1);
};

$manifest = json_decode((string) file_get_contents($packageRoot . '/opus.application.json'), true);
if (!is_array($manifest) || ($manifest['contract'] ?? '') !== 'OPUS_APPLICATION_PACKAGE_MANIFEST_V1') {
    $fail('CHECK_LSTSAR_MANAGER_MANIFEST');
}
foreach (['lstsar_manager_package_core', 'model_driven_odbc', 'odbc_only', 'declarative_backoffice', 'dry_run_enabled'] as $flag) {
    if (($manifest['metadata'][$flag] ?? false) !== true) {
        $fail('CHECK_LSTSAR_MANAGER_MANIFEST_FLAG', $flag);
    }
}
if (($manifest['metadata']['execution_enabled'] ?? true) !== false || ($manifest['metadata']['raw_sql_allowed'] ?? true) !== false || ($manifest['metadata']['ddl_enabled'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_FORBIDDEN_FLAGS');
}
echo "CHECK_LSTSAR_MANAGER_MANIFEST=OK
";

$composer = json_decode((string) file_get_contents($packageRoot . '/composer.json'), true);
if (($composer['autoload']['psr-4']['OpusLstsarManager\\'] ?? '') !== 'src/') {
    $fail('CHECK_LSTSAR_MANAGER_COMPOSER');
}
echo "CHECK_LSTSAR_MANAGER_COMPOSER=OK\n";

$routes = require $packageRoot . '/app/routes.php';
$requiredRoutes = [
    'opus_lstsar_manager_dashboard' => ['template' => 'dashboard.score', 'methods' => ['GET'], 'permission' => 'opus.lstsar_manager.access'],
    'opus_lstsar_manager_declarations' => ['template' => 'declarations.score', 'methods' => ['GET'], 'permission' => 'opus.lstsar_manager.declare'],
    'opus_lstsar_manager_sources' => ['template' => 'endpoint.score', 'methods' => ['GET'], 'permission' => 'opus.lstsar_manager.source'],
    'opus_lstsar_manager_destinations' => ['template' => 'endpoint.score', 'methods' => ['GET'], 'permission' => 'opus.lstsar_manager.destination'],
    'opus_lstsar_manager_mappings' => ['template' => 'mapping.score', 'methods' => ['GET'], 'permission' => 'opus.lstsar_manager.mapping'],
    'opus_lstsar_manager_rules' => ['template' => 'rules.score', 'methods' => ['GET'], 'permission' => 'opus.lstsar_manager.rules'],
    'opus_lstsar_manager_archive_report' => ['template' => 'archive-report.score', 'methods' => ['GET'], 'permission' => 'opus.lstsar_manager.archive_report'],
    'opus_lstsar_manager_dry_run' => ['template' => 'dry-run.score', 'methods' => ['GET'], 'permission' => 'opus.lstsar_manager.dry_run'],
    'opus_lstsar_manager_dry_run_preview' => ['template' => 'dry-run.score', 'methods' => ['POST'], 'permission' => 'opus.lstsar_manager.dry_run'],
];
foreach ($requiredRoutes as $name => $expected) {
    if (!isset($routes[$name]) || ($routes[$name]['template'] ?? '') !== $expected['template'] || ($routes[$name]['methods'] ?? []) !== $expected['methods'] || ($routes[$name]['permission'] ?? '') !== $expected['permission']) {
        $fail('CHECK_LSTSAR_MANAGER_ROUTE_CONTRACT', $name);
    }
    if (!is_file($packageRoot . '/templates/' . $expected['template'])) {
        $fail('CHECK_LSTSAR_MANAGER_TEMPLATE_MISSING', $expected['template']);
    }
}
echo "CHECK_LSTSAR_MANAGER_ROUTES=OK
";
echo "CHECK_LSTSAR_MANAGER_TEMPLATES=OK
";

foreach ($routes as $route) {
    $path = strtolower((string) ($route['path'] ?? ''));
    foreach (['drop', 'alter', 'create-table', 'sql', 'execute', 'ddl'] as $forbidden) {
        if (str_contains($path, $forbidden)) {
            $fail('CHECK_LSTSAR_MANAGER_NO_FORBIDDEN_ROUTES', $path);
        }
    }
}
echo "CHECK_LSTSAR_MANAGER_NO_FORBIDDEN_ROUTES=OK
";

$acl = require $packageRoot . '/config/acl.php';
foreach (['opus.lstsar_manager.access', 'opus.lstsar_manager.declare', 'opus.lstsar_manager.source', 'opus.lstsar_manager.destination', 'opus.lstsar_manager.mapping', 'opus.lstsar_manager.rules', 'opus.lstsar_manager.archive_report', 'opus.lstsar_manager.dry_run'] as $permission) {
    if (!isset($acl['permissions'][$permission])) {
        $fail('CHECK_LSTSAR_MANAGER_ACL_PERMISSION', $permission);
    }
}
if (($acl['anonymous'] ?? true) !== false || ($acl['denied_by_default'] ?? false) !== true || ($acl['lstsar_policy']['raw_sql_allowed'] ?? true) !== false || ($acl['lstsar_policy']['ddl_allowed'] ?? true) !== false || ($acl['lstsar_policy']['execute_allowed'] ?? true) !== false || ($acl['lstsar_policy']['mapping_required'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_MANAGER_POLICY');
}
echo "CHECK_LSTSAR_MANAGER_ACL=OK
";

$navigation = require $packageRoot . '/config/navigation.php';
$navRoutes = array_map(static fn (array $item): string => (string) ($item['route'] ?? ''), $navigation['items'] ?? []);
foreach (['opus_lstsar_manager_declarations', 'opus_lstsar_manager_sources', 'opus_lstsar_manager_destinations', 'opus_lstsar_manager_mappings', 'opus_lstsar_manager_dry_run'] as $routeName) {
    if (!in_array($routeName, $navRoutes, true)) {
        $fail('CHECK_LSTSAR_MANAGER_NAVIGATION', $routeName);
    }
}
echo "CHECK_LSTSAR_MANAGER_NAVIGATION=OK
";

$profilerConfig = require $packageRoot . '/config/profiler.php';
foreach (['dashboard', 'declarations', 'sources', 'destinations', 'mappings', 'rules', 'archive_report', 'dry_run_form', 'dry_run_preview'] as $action) {
    if (!in_array($action, $profilerConfig['actions'] ?? [], true)) {
        $fail('CHECK_LSTSAR_MANAGER_PROFILER_CONFIG', $action);
    }
}
echo "CHECK_LSTSAR_MANAGER_PROFILER_CONFIG=OK
";

$fr = require $packageRoot . '/i18n/fr.php';
$en = require $packageRoot . '/i18n/en.php';
foreach (['lstsar_manager.title', 'lstsar_manager.declarations', 'lstsar_manager.sources', 'lstsar_manager.destinations', 'lstsar_manager.mappings', 'lstsar_manager.rules', 'lstsar_manager.archive_report', 'lstsar_manager.dry_run', 'lstsar_manager.no_raw_sql'] as $key) {
    if (!isset($fr[$key], $en[$key])) {
        $fail('CHECK_LSTSAR_MANAGER_I18N', $key);
    }
}
echo "CHECK_LSTSAR_MANAGER_I18N=OK
";

$repository = new LstsarManagerDeclarationRepository();
$declaration = $repository->sampleDeclarationArray();
if (($declaration['contract'] ?? '') !== 'OPUS_LSTSAR_BACKOFFICE_DECLARATION_V1' || ($declaration['config']['contract'] ?? '') !== 'OPUS_LSTSAR_MODEL_DRIVEN_ODBC_CONFIG_V1') {
    $fail('CHECK_LSTSAR_MANAGER_DECLARATION_CONTRACT');
}
foreach (['source', 'destination', 'mapping', 'security', 'transform', 'archive', 'report'] as $section) {
    if (!in_array($section, $declaration['editable_sections'] ?? [], true)) {
        $fail('CHECK_LSTSAR_MANAGER_DECLARATION_SECTION', $section);
    }
}
echo "CHECK_LSTSAR_MANAGER_DECLARATION=OK
";

$service = new LstsarManagerDryRunService($repository);
$factory = new LstsarManagerViewModelFactory($repository, $service);
$dashboard = $factory->dashboard();
if (($dashboard['mode'] ?? '') !== 'lstsar_manager' || ($dashboard['capabilities']['raw_sql'] ?? true) !== false || ($dashboard['capabilities']['ddl'] ?? true) !== false || ($dashboard['capabilities']['execute'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_VM');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_VM=OK
";

if (($factory->endpoint('source')['endpoint_type'] ?? '') !== 'source' || ($factory->endpoint('destination')['endpoint_type'] ?? '') !== 'destination') {
    $fail('CHECK_LSTSAR_MANAGER_ENDPOINT_VM');
}
if (($factory->mappings()['mapping_required'] ?? false) !== true || !isset($factory->rules()['rules']['security'], $factory->rules()['rules']['transform'], $factory->archiveReport()['archive'], $factory->archiveReport()['report'])) {
    $fail('CHECK_LSTSAR_MANAGER_VIEW_MODELS_BASIC');
}
$dryRun = $factory->dryRun(['sample' => true]);
if (($dryRun['dry_run'] ?? false) !== true || ($dryRun['execution_enabled'] ?? true) !== false || ($dryRun['preview']['would_execute'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_VM');
}
echo "CHECK_LSTSAR_MANAGER_VIEW_MODELS=OK
";

if ((new DashboardController($factory))->dashboard()['mode'] !== 'lstsar_manager') {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_CONTROLLER');
}
$declarationsController = new DeclarationsController($factory);
if (($declarationsController->sources()['endpoint_type'] ?? '') !== 'source' || ($declarationsController->destinations()['endpoint_type'] ?? '') !== 'destination') {
    $fail('CHECK_LSTSAR_MANAGER_DECLARATIONS_CONTROLLER');
}
$dryRunController = new DryRunController($factory);
if (($dryRunController->preview(['sample' => true])['dry_run'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_CONTROLLER');
}
echo "CHECK_LSTSAR_MANAGER_CONTROLLERS=OK
";

$profiler = LstsarManagerProfiler::enabled();
$profiled = (new DashboardController($factory, $profiler))->dashboard();
if (($profiled['mode'] ?? '') !== 'lstsar_manager' || count($profiler->events()) !== 2) {
    $fail('CHECK_LSTSAR_MANAGER_PROFILER');
}
echo "CHECK_LSTSAR_MANAGER_PROFILER=OK
";

echo "P7_LSTSAR_MANAGER_PACKAGE_CORE_SMOKE_OK
";
