<?php
declare(strict_types=1);

use OpusLstsarManager\Config\LstsarManagerDeclarationRepository;
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
require_once $packageRoot . '/src/Controller/DryRunController.php';

require_once $root . '/Opus/Lstsar/01_Load.php';
require_once $root . '/Opus/Lstsar/02_Secure.php';
require_once $root . '/Opus/Lstsar/03_Transform.php';
require_once $root . '/Opus/Lstsar/04_Store.php';
require_once $root . '/Opus/Lstsar/05_Archive.php';
require_once $root . '/Opus/Lstsar/06_Report.php';

echo "P7_LSTSAR_MANAGER_DRY_RUN_INTEGRATION_CORE_SMOKE
";

$fail = static function (string $check, string $detail = ''): void {
    echo $check . '=FAIL' . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
    exit(1);
};

$manifest = json_decode((string) file_get_contents($packageRoot . '/opus.application.json'), true);
if (!is_array($manifest) || ($manifest['metadata']['lstsar_manager_dry_run_integration_core'] ?? false) !== true || ($manifest['metadata']['dry_run_engine_integrated'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_MANIFEST');
}
if (($manifest['metadata']['execution_enabled'] ?? true) !== false || ($manifest['metadata']['direct_execute_allowed'] ?? true) !== false || ($manifest['metadata']['raw_sql_allowed'] ?? true) !== false || ($manifest['metadata']['ddl_enabled'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_FORBIDDEN_FLAGS');
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_MANIFEST=OK
";

$acl = require $packageRoot . '/config/acl.php';
if (($acl['lstsar_policy']['dry_run_engine_integration'] ?? false) !== true || ($acl['lstsar_policy']['execute_allowed'] ?? true) !== false || ($acl['lstsar_policy']['direct_execute_allowed'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_ACL_POLICY');
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_ACL=OK
";

$routes = require $packageRoot . '/app/routes.php';
foreach ($routes as $route) {
    $path = strtolower((string) ($route['path'] ?? ''));
    foreach (['drop', 'alter', 'create-table', 'sql', 'execute', 'ddl'] as $forbidden) {
        if (str_contains($path, $forbidden)) {
            $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_NO_FORBIDDEN_ROUTES', $path);
        }
    }
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_NO_FORBIDDEN_ROUTES=OK
";

$profilerConfig = require $packageRoot . '/config/profiler.php';
if (!in_array('dry_run_engine_preview', $profilerConfig['actions'] ?? [], true)) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_PROFILER_CONFIG');
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_PROFILER_CONFIG=OK
";

$repository = new LstsarManagerDeclarationRepository();
$config = $repository->sampleConfig();
$sourceModel = $repository->sampleSourceModel();
$destinationModel = $repository->sampleDestinationModel();
if (($config->source()['model'] ?? '') !== $sourceModel->id() || ($config->destination()['model'] ?? '') !== $destinationModel->id()) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_MODELS_MATCH_CONFIG');
}
if (($config->mapping()['code'] ?? '') !== 'order_code' || ($config->transform()['order_code']['uppercase'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_MAPPING_TRANSFORM');
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_DECLARATION_MODELS=OK
";

$service = new LstsarManagerDryRunService($repository);
$preview = $service->preview(['source_record' => ['code' => ' ab ', 'amount' => '12.345']]);
if (($preview['contract'] ?? '') !== 'OPUS_LSTSAR_MANAGER_DRY_RUN_INTEGRATION_V1' || ($preview['dry_run'] ?? false) !== true || ($preview['would_execute'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_PREVIEW_CONTRACT');
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_PREVIEW_CONTRACT=OK
";

if (($preview['run_result']['contract'] ?? '') !== 'OPUS_LSTSAR_MODEL_DRIVEN_ODBC_RUN_RESULT_V1' || ($preview['run_result']['ok'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_ENGINE_RESULT');
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_ENGINE_RESULT=OK
";

$record = $preview['transformed_record'] ?? [];
if (($record['order_code'] ?? '') !== 'AB00' || ($record['total_amount'] ?? null) !== 12.35) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_TRANSFORMED_RECORD', var_export($record, true));
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_TRANSFORMED_RECORD=OK
";

foreach (['load', 'securize', 'transform', 'store', 'archive', 'report'] as $stage) {
    if (!isset($preview['run_result']['stage_results'][$stage])) {
        $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_STAGE_RESULT', $stage);
    }
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_STAGE_RESULTS=OK
";

if (empty($preview['destination_record_id']) || empty($preview['archive_record_id']) || ($preview['run_result']['report']['ok'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_REPORT_ARCHIVE');
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_REPORT_ARCHIVE=OK
";

$factory = new LstsarManagerViewModelFactory($repository, $service);
$vm = $factory->dryRun(['source_record' => ['code' => ' cd ', 'amount' => '7.891']]);
if (($vm['dry_run_engine_integrated'] ?? false) !== true || ($vm['preview']['run_result']['ok'] ?? false) !== true || ($vm['preview']['would_execute'] ?? true) !== false) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_VIEW_MODEL');
}
if (($vm['preview']['transformed_record']['order_code'] ?? '') !== 'CD00') {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_VIEW_MODEL_RECORD');
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_VIEW_MODEL=OK
";

$controller = new DryRunController($factory);
$controllerResult = $controller->preview(['source_record' => ['code' => ' ef ', 'amount' => '1.234']]);
if (($controllerResult['preview']['run_result']['ok'] ?? false) !== true || ($controllerResult['preview']['transformed_record']['order_code'] ?? '') !== 'EF00') {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_CONTROLLER');
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_CONTROLLER=OK
";

$denied = $service->preview(['security' => ['acl_granted' => false]]);
if (($denied['run_result']['ok'] ?? true) !== false || (($denied['run_result']['violations'][0]['code'] ?? '') !== 'OPUS_LSTSAR_SECURIZE_DENIED')) {
    $fail('CHECK_LSTSAR_MANAGER_DRY_RUN_SECURIZE_REJECTS');
}
echo "CHECK_LSTSAR_MANAGER_DRY_RUN_SECURIZE_REJECTS=OK
";

echo "P7_LSTSAR_MANAGER_DRY_RUN_INTEGRATION_CORE_SMOKE_OK
";
