<?php
declare(strict_types=1);

use OpusLstsarManager\Config\LstsarManagerDeclarationRepository;
use OpusLstsarManager\Controller\DashboardController;
use OpusLstsarManager\Controller\OperationsController;
use OpusLstsarManager\Diagnostics\LstsarManagerProfiler;
use OpusLstsarManager\Operation\LstsarManagerOperationRepository;
use OpusLstsarManager\View\LstsarManagerViewModelFactory;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$packageRoot = $root . '/packages/opus-lstsar-manager';
require_once $packageRoot . '/src/Diagnostics/LstsarManagerProfiler.php';
require_once $packageRoot . '/src/Config/LstsarManagerDeclarationRepository.php';
require_once $packageRoot . '/src/DryRun/LstsarManagerDryRunService.php';
require_once $packageRoot . '/src/Operation/LstsarManagerOperationRepository.php';
require_once $packageRoot . '/src/View/LstsarManagerViewModelFactory.php';
require_once $packageRoot . '/src/Controller/DashboardController.php';
require_once $packageRoot . '/src/Controller/OperationsController.php';

echo "P7_LSTSAR_MANAGER_DASHBOARD_OPERATIONS_CORE_SMOKE\n";

$fail = static function (string $check, string $detail = ''): void {
    echo $check . '=FAIL' . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
    exit(1);
};

$manifest = json_decode((string) file_get_contents($packageRoot . '/opus.application.json'), true);
if (!is_array($manifest) || ($manifest['metadata']['lstsar_manager_dashboard_operations_core'] ?? false) !== true || ($manifest['metadata']['site_client_operations_dashboard'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_MANIFEST');
}
if (($manifest['metadata']['manual_launch_enabled'] ?? true) !== false || ($manifest['metadata']['scheduler_launch_enabled'] ?? true) !== false || ($manifest['metadata']['raw_sql_allowed'] ?? true) !== false || ($manifest['metadata']['ddl_enabled'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_FORBIDDEN_FLAGS');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_MANIFEST=OK\n";

$routes = require $packageRoot . '/app/routes.php';
if (!isset($routes['opus_lstsar_manager_operations'])) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_ROUTE_MISSING');
}
$route = $routes['opus_lstsar_manager_operations'];
if (($route['path'] ?? '') !== '/opus-lstsar-manager/operations' || ($route['template'] ?? '') !== 'operations.score' || ($route['methods'] ?? []) !== ['GET'] || ($route['permission'] ?? '') !== 'opus.lstsar_manager.operations') {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_ROUTE_CONTRACT');
}
if (!is_file($packageRoot . '/templates/operations.score')) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_TEMPLATE');
}
foreach ($routes as $candidate) {
    $path = strtolower((string) ($candidate['path'] ?? ''));
    foreach (['drop', 'alter', 'create-table', 'sql', 'execute', 'ddl'] as $forbidden) {
        if (str_contains($path, $forbidden)) {
            $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_NO_FORBIDDEN_ROUTES', $path);
        }
    }
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_ROUTES=OK\n";

echo "CHECK_LSTSAR_MANAGER_DASHBOARD_TEMPLATES=OK\n";

$acl = require $packageRoot . '/config/acl.php';
if (!isset($acl['permissions']['opus.lstsar_manager.operations'])) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_ACL_PERMISSION');
}
if (($acl['lstsar_policy']['operations_dashboard_enabled'] ?? false) !== true || ($acl['lstsar_policy']['site_client_scoped_operations'] ?? false) !== true || ($acl['lstsar_policy']['manual_launch_allowed'] ?? true) !== false || ($acl['lstsar_policy']['scheduler_launch_allowed'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_ACL_POLICY');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_ACL=OK\n";

$navigation = require $packageRoot . '/config/navigation.php';
$navRoutes = array_map(static fn (array $item): string => (string) ($item['route'] ?? ''), $navigation['items'] ?? []);
if (!in_array('opus_lstsar_manager_operations', $navRoutes, true)) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_NAVIGATION');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_NAVIGATION=OK\n";

$profilerConfig = require $packageRoot . '/config/profiler.php';
if (!in_array('operations', $profilerConfig['actions'] ?? [], true)) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_PROFILER_CONFIG');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_PROFILER_CONFIG=OK\n";

$fr = require $packageRoot . '/i18n/fr.php';
$en = require $packageRoot . '/i18n/en.php';
foreach (['lstsar_manager.operations', 'lstsar_manager.dashboard_operations', 'lstsar_manager.mapping_assignment_coverage', 'lstsar_manager.next_run', 'lstsar_manager.last_run', 'lstsar_manager.last_dry_run'] as $key) {
    if (!isset($fr[$key], $en[$key])) {
        $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_I18N', $key);
    }
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_I18N=OK\n";

$declarations = new LstsarManagerDeclarationRepository();
$config = $declarations->sampleConfig();
$destinationModel = $declarations->sampleDestinationModel();
if (!$destinationModel->hasField('client_id') || !$destinationModel->hasField('row_hash') || !isset($config->transform()['assignments']['client_id'], $config->transform()['assignments']['row_hash'])) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_ASSIGNMENT_DECLARATION');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_ASSIGNMENT_DECLARATION=OK\n";

$operationsRepository = new LstsarManagerOperationRepository($declarations);
$dashboard = $operationsRepository->dashboardForSite('site-alpha');
if (($dashboard['contract'] ?? '') !== 'OPUS_LSTSAR_MANAGER_DASHBOARD_OPERATIONS_V1' || ($dashboard['selected_site']['site_id'] ?? '') !== 'site-alpha' || ($dashboard['counters']['operations'] ?? 0) !== 1) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_CONTRACT');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_CONTRACT=OK\n";

$operation = $dashboard['operations'][0] ?? [];
if (($operation['contract'] ?? '') !== 'OPUS_LSTSAR_MANAGER_OPERATION_V1' || ($operation['operation_id'] ?? '') !== 'lstsar.orders.import' || ($operation['site_id'] ?? '') !== 'site-alpha' || ($operation['client_id'] ?? '') !== 'client-demo') {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_OPERATION_IDENTITY');
}
if (($operation['active'] ?? false) !== true || ($operation['status'] ?? '') !== 'ready') {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_OPERATION_STATUS');
}
if (($operation['source']['driver'] ?? '') !== 'odbc' || ($operation['destination']['driver'] ?? '') !== 'odbc') {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_OPERATION_ENDPOINTS');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_OPERATION=OK\n";

$coverage = $operation['coverage'] ?? [];
foreach (['order_code', 'total_amount'] as $mappedField) {
    if (!in_array($mappedField, $coverage['mapped_fields'] ?? [], true)) {
        $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_MAPPING_COVERAGE', $mappedField);
    }
}
foreach (['client_id', 'created_by', 'row_hash'] as $assignedField) {
    if (!in_array($assignedField, $coverage['assigned_fields'] ?? [], true)) {
        $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_ASSIGNMENT_COVERAGE', $assignedField);
    }
}
if (($coverage['coverage_ok'] ?? false) !== true || ($coverage['missing_required_fields'] ?? []) !== []) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_COVERAGE_OK', var_export($coverage, true));
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_COVERAGE=OK\n";

if (($operation['last_dry_run']['ok'] ?? false) !== true || ($operation['last_run']['ok'] ?? false) !== true || ($operation['next_run']['trigger'] ?? '') !== 'cron' || ($operation['next_run']['enabled'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_RUN_SUMMARY');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_RUN_SUMMARY=OK\n";

$actions = [];
foreach ($operation['launch_actions'] ?? [] as $action) {
    if (is_array($action) && isset($action['name'])) {
        $actions[(string) $action['name']] = $action;
    }
}
if (($actions['dry_run']['enabled'] ?? false) !== true || ($actions['manual_run']['enabled'] ?? true) !== false || ($actions['cron_trigger']['enabled'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_ACTIONS');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_ACTIONS=OK\n";

$factory = new LstsarManagerViewModelFactory($declarations, null, $operationsRepository);
$dashboardVm = $factory->dashboard('site-alpha');
if (($dashboardVm['capabilities']['dashboard_operations'] ?? false) !== true || ($dashboardVm['capabilities']['manual_launch'] ?? true) !== false || ($dashboardVm['operation_counters']['operations'] ?? 0) !== 1) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_VIEW_MODEL');
}
$operationsVm = $factory->operations('site-alpha');
if (($operationsVm['operations_dashboard']['contract'] ?? '') !== 'OPUS_LSTSAR_MANAGER_DASHBOARD_OPERATIONS_V1' || ($operationsVm['counters']['ready'] ?? 0) !== 1) {
    $fail('CHECK_LSTSAR_MANAGER_OPERATIONS_VIEW_MODEL');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_VIEW_MODELS=OK\n";

$dashboardController = new DashboardController($factory);
if (($dashboardController->dashboard('site-alpha')['operations_dashboard']['selected_site']['site_id'] ?? '') !== 'site-alpha') {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_CONTROLLER');
}
$operationsController = new OperationsController($factory);
if (($operationsController->operations('site-alpha')['operations'][0]['status'] ?? '') !== 'ready') {
    $fail('CHECK_LSTSAR_MANAGER_OPERATIONS_CONTROLLER');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_CONTROLLERS=OK\n";

$profiler = LstsarManagerProfiler::enabled();
$profiled = (new OperationsController($factory, $profiler))->operations('site-alpha');
if (($profiled['operations'][0]['operation_id'] ?? '') !== 'lstsar.orders.import' || count($profiler->events()) !== 2) {
    $fail('CHECK_LSTSAR_MANAGER_DASHBOARD_PROFILER');
}
echo "CHECK_LSTSAR_MANAGER_DASHBOARD_PROFILER=OK\n";

echo "P7_LSTSAR_MANAGER_DASHBOARD_OPERATIONS_CORE_SMOKE_OK\n";
