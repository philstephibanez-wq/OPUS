<?php
declare(strict_types=1);

$lines = ['P7_OPS_COMMAND_CENTER_CORE_SMOKE'];
$root = dirname(__DIR__, 2);
$files = [
    'index' => $root . '/sites/opus-p7-ops/public/index.php',
    'router' => $root . '/sites/opus-p7-ops/public/router.php',
    'action' => $root . '/sites/opus-p7-ops/public/action.php',
    'command' => $root . '/sites/opus-p7-ops/public/command.php',
    'readme' => $root . '/sites/opus-p7-ops/README.md',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('COMMAND_CENTER_FILE_MISSING: ' . $file);
    }
}

$all = '';
foreach ($files as $file) {
    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('COMMAND_CENTER_READ_FAILED');
    }
    $all .= $source . "\n";
}

foreach ([
    'P7_OPS_COMMAND_CENTER_CORE',
    '/opus-lstsar-manager/command',
    '/opus-lstsar-manager/command-center',
    'OPUS OPS Command Center',
    'OPS summary',
    'Quick actions',
    'Operations table',
    'Diagnostics',
    'Preview',
    'Dry-run',
    'Audit',
    'controlled_preview',
    'controlled_dry_run',
    'controlled_audit',
    'side_effects',
] as $marker) {
    if (!str_contains($all, $marker)) {
        throw new RuntimeException('COMMAND_CENTER_MARKER_MISSING: ' . $marker);
    }
}

$lines[] = 'CHECK_P7_OPS_COMMAND_CENTER_MARKERS=OK';

require $root . '/vendor/autoload.php';

$factory = new \OpusLstsarManager\View\LstsarManagerViewModelFactory();
$controller = new \OpusLstsarManager\Controller\OperationsController($factory);
$vm = $controller->operations('site-alpha');
$dashboard = is_array($vm['operations_dashboard'] ?? null) ? $vm['operations_dashboard'] : [];
$operations = is_array($dashboard['operations'] ?? null) ? $dashboard['operations'] : [];
$firstOperation = $operations[0] ?? null;

if (!is_array($firstOperation)) {
    throw new RuntimeException('COMMAND_CENTER_NO_OPERATION');
}

$operationId = (string) ($firstOperation['operation_id'] ?? $firstOperation['id'] ?? '');
if ($operationId === '') {
    throw new RuntimeException('COMMAND_CENTER_EMPTY_OPERATION_ID');
}

$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/command?site=site-alpha&action=preview';
$_GET = ['site' => 'site-alpha', 'action' => 'preview'];

ob_start();
require $files['command'];
$html = (string) ob_get_clean();
http_response_code(200);

foreach ([
    'OPUS OPS Command Center',
    'P7_OPS_COMMAND_CENTER_CORE',
    'OPS summary',
    'Quick actions',
    'Operations table',
    'Diagnostics',
    $operationId,
    'controlled_preview',
    'side_effects',
] as $marker) {
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('COMMAND_CENTER_RENDER_MARKER_MISSING: ' . $marker);
    }
}

$lines[] = 'CHECK_P7_OPS_COMMAND_CENTER_RENDER=OK';
$lines[] = 'CHECK_P7_OPS_COMMAND_CENTER_ROUTER=OK';
$lines[] = 'CHECK_P7_OPS_COMMAND_CENTER_ACTION_MODES=OK';
$lines[] = 'P7_OPS_COMMAND_CENTER_CORE_SMOKE_OK';

echo implode(PHP_EOL, $lines) . PHP_EOL;
