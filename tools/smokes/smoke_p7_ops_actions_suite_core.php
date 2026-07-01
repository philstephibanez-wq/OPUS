<?php
declare(strict_types=1);

$lines = [];
$lines[] = 'P7_OPS_ACTIONS_SUITE_CORE_SMOKE';

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';
$siteDir = $root . '/sites/opus-p7-ops';

$indexFile = $publicDir . '/index.php';
$routerFile = $publicDir . '/router.php';
$actionFile = $publicDir . '/action.php';
$readmeFile = $siteDir . '/README.md';

foreach ([$indexFile, $routerFile, $actionFile, $readmeFile] as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPS_SUITE_FILE_MISSING: ' . $file);
    }
}

$index = file_get_contents($indexFile);
$router = file_get_contents($routerFile);
$actionSource = file_get_contents($actionFile);
$readme = file_get_contents($readmeFile);

if ($index === false || $router === false || $actionSource === false || $readme === false) {
    throw new RuntimeException('OPS_SUITE_READ_FAILED');
}

$combined = $index . "\n" . $router . "\n" . $actionSource . "\n" . $readme;

foreach ([
    '/opus-lstsar-manager/action?site=',
    '/opus-lstsar-manager/action',
    'OPUS_LSTSAR_MANAGER_OPERATION_ACTION_V1',
    'Action OPS controlee',
    'controlled_preview',
    'controlled_dry_run',
    'controlled_audit',
    'side_effects',
    'P7_OPS_ACTIONS_SUITE_CORE',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('OPS_SUITE_MARKER_MISSING: ' . $marker);
    }
}

$lines[] = 'CHECK_P7_OPS_ACTIONS_SUITE_MARKERS=OK';

require $root . '/vendor/autoload.php';

$factory = new \OpusLstsarManager\View\LstsarManagerViewModelFactory();
$controller = new \OpusLstsarManager\Controller\OperationsController($factory);
$vm = $controller->operations('site-alpha');
$dashboard = is_array($vm['operations_dashboard'] ?? null) ? $vm['operations_dashboard'] : [];
$operations = is_array($dashboard['operations'] ?? null) ? $dashboard['operations'] : [];
$firstOperation = $operations[0] ?? null;

if (!is_array($firstOperation)) {
    throw new RuntimeException('OPS_SUITE_NO_OPERATION_AVAILABLE');
}

$operationId = (string) ($firstOperation['operation_id'] ?? $firstOperation['id'] ?? '');
if ($operationId === '') {
    throw new RuntimeException('OPS_SUITE_OPERATION_ID_EMPTY');
}

function ops_suite_render_action(string $actionFile, string $site, string $operationId, string $action): array
{
    $_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/action?site=' . rawurlencode($site) . '&operation=' . rawurlencode($operationId) . '&action=' . rawurlencode($action);
    $_GET = [
        'site' => $site,
        'operation' => $operationId,
        'action' => $action,
    ];

    ob_start();
    require $actionFile;
    $html = (string) ob_get_clean();
    $status = http_response_code();
    http_response_code(200);

    return [$html, $status];
}

function ops_suite_assert_contains(string $html, string $marker): void
{
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('OPS_SUITE_RENDER_MARKER_MISSING: ' . $marker);
    }
}

foreach ([
    'preview' => 'controlled_preview',
    'dry-run' => 'controlled_dry_run',
    'audit' => 'controlled_audit',
] as $action => $mode) {
    [$html, $status] = ops_suite_render_action($actionFile, 'site-alpha', $operationId, $action);

    if ($status !== 200) {
        throw new RuntimeException('OPS_SUITE_ACTION_STATUS_INVALID: ' . $action . '=' . (string) $status);
    }

    foreach ([
        'Action OPS controlee',
        'OPUS_LSTSAR_MANAGER_OPERATION_ACTION_V1',
        $operationId,
        $mode,
        'false',
        'error',
    ] as $marker) {
        ops_suite_assert_contains($html, $marker);
    }
}

$lines[] = 'CHECK_P7_OPS_ACTIONS_SUITE_RENDER_OK=OK';

[$badActionHtml, $badActionStatus] = ops_suite_render_action($actionFile, 'site-alpha', $operationId, 'bad-action');
if ($badActionStatus !== 400) {
    throw new RuntimeException('OPS_SUITE_BAD_ACTION_STATUS_INVALID: ' . (string) $badActionStatus);
}
ops_suite_assert_contains($badActionHtml, 'Unknown action: bad-action');

[$badOperationHtml, $badOperationStatus] = ops_suite_render_action($actionFile, 'site-alpha', 'missing.operation', 'preview');
if ($badOperationStatus !== 404) {
    throw new RuntimeException('OPS_SUITE_BAD_OPERATION_STATUS_INVALID: ' . (string) $badOperationStatus);
}
ops_suite_assert_contains($badOperationHtml, 'Unknown operation: missing.operation');

$lines[] = 'CHECK_P7_OPS_ACTIONS_SUITE_ERRORS_OK=OK';

function ops_suite_render_route(string $routerFile, string $site, string $operationId): string
{
    $_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/action?site=' . rawurlencode($site) . '&operation=' . rawurlencode($operationId) . '&action=preview';
    $_GET = [
        'site' => $site,
        'operation' => $operationId,
        'action' => 'preview',
    ];

    ob_start();
    require $routerFile;
    $html = (string) ob_get_clean();
    http_response_code(200);

    return $html;
}

$routeHtml = ops_suite_render_route($routerFile, 'site-alpha', $operationId);
ops_suite_assert_contains($routeHtml, 'Action OPS controlee');
ops_suite_assert_contains($routeHtml, 'controlled_preview');

$lines[] = 'CHECK_P7_OPS_ACTIONS_SUITE_ROUTER_OK=OK';
$lines[] = 'P7_OPS_ACTIONS_SUITE_CORE_SMOKE_OK';

echo implode(PHP_EOL, $lines) . PHP_EOL;
