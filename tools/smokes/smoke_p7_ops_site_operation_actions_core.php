<?php
declare(strict_types=1);

$lines = [];
$lines[] = 'P7_OPS_SITE_OPERATION_ACTIONS_CORE_SMOKE';

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';
$indexFile = $publicDir . '/index.php';
$routerFile = $publicDir . '/router.php';
$actionFile = $publicDir . '/action.php';

foreach ([$indexFile, $routerFile, $actionFile] as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPS_ACTION_FILE_MISSING: ' . $file);
    }
}

$index = file_get_contents($indexFile);
$router = file_get_contents($routerFile);
$action = file_get_contents($actionFile);

if ($index === false || $router === false || $action === false) {
    throw new RuntimeException('OPS_ACTION_READ_FAILED');
}

$combined = $index . "\n" . $router . "\n" . $action;

foreach ([
    '/opus-lstsar-manager/action?site=',
    '/opus-lstsar-manager/action',
    'OPUS_LSTSAR_MANAGER_OPERATION_ACTION_V1',
    'Action OPS controlee',
    'controlled_preview',
    'side_effects',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('OPS_ACTION_MARKER_MISSING: ' . $marker);
    }
}

$lines[] = 'CHECK_P7_OPS_SITE_ACTION_MARKERS=OK';

require $root . '/vendor/autoload.php';

$factory = new \OpusLstsarManager\View\LstsarManagerViewModelFactory();
$controller = new \OpusLstsarManager\Controller\OperationsController($factory);
$vm = $controller->operations('site-alpha');
$dashboard = is_array($vm['operations_dashboard'] ?? null) ? $vm['operations_dashboard'] : [];
$operations = is_array($dashboard['operations'] ?? null) ? $dashboard['operations'] : [];
$firstOperation = $operations[0] ?? null;

if (!is_array($firstOperation)) {
    throw new RuntimeException('OPS_ACTION_NO_OPERATION_AVAILABLE');
}

$operationId = (string) ($firstOperation['operation_id'] ?? $firstOperation['id'] ?? '');
if ($operationId === '') {
    throw new RuntimeException('OPS_ACTION_OPERATION_ID_EMPTY');
}

$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/action?site=site-alpha&operation=' . $operationId . '&action=preview';
$_GET = [
    'site' => 'site-alpha',
    'operation' => $operationId,
    'action' => 'preview',
];

ob_start();
require $actionFile;
$html = (string) ob_get_clean();

foreach ([
    'Action OPS controlee',
    'OPUS_LSTSAR_MANAGER_OPERATION_ACTION_V1',
    $operationId,
    'controlled_preview',
    'false',
    'error',
] as $marker) {
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('OPS_ACTION_RENDER_MARKER_MISSING: ' . $marker);
    }
}

$lines[] = 'CHECK_P7_OPS_SITE_ACTION_RENDER=OK';
$lines[] = 'P7_OPS_SITE_OPERATION_ACTIONS_CORE_SMOKE_OK';

echo implode(PHP_EOL, $lines) . PHP_EOL;
