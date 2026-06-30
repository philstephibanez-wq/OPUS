<?php
declare(strict_types=1);

echo 'P7_OPS_SITE_OPERATIONS_UI_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$indexFile = $root . '/sites/opus-p7-ops/public/index.php';

if (!is_file($indexFile)) {
    throw new RuntimeException('OPS_INDEX_NOT_FOUND');
}

$index = file_get_contents($indexFile);
if ($index === false) {
    throw new RuntimeException('OPS_INDEX_READ_FAILED');
}

foreach ([
    '<h2>Compteurs OPS</h2>',
    'class="ops-table"',
    'Preview',
    'Dry-run',
    'Audit',
    'Afficher JSON brut',
] as $marker) {
    if (!str_contains($index, $marker)) {
        throw new RuntimeException('OPS_UI_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_SITE_UI_MARKERS=OK' . PHP_EOL;

require $root . '/vendor/autoload.php';

$factory = new \OpusLstsarManager\View\LstsarManagerViewModelFactory();
$controller = new \OpusLstsarManager\Controller\OperationsController($factory);
$vm = $controller->operations('site-alpha');

$dashboard = $vm['operations_dashboard'] ?? null;
if (!is_array($dashboard)) {
    throw new RuntimeException('OPS_DASHBOARD_VM_MISSING');
}

$counters = $dashboard['counters'] ?? null;
if (!is_array($counters) || !array_key_exists('operations', $counters) || !array_key_exists('active', $counters) || !array_key_exists('ready', $counters) || !array_key_exists('blocked', $counters)) {
    throw new RuntimeException('OPS_COUNTERS_INVALID');
}

$operations = $dashboard['operations'] ?? null;
if (!is_array($operations) || count($operations) < 1) {
    throw new RuntimeException('OPS_OPERATIONS_LIST_INVALID');
}

echo 'CHECK_P7_OPS_SITE_COUNTERS=OK' . PHP_EOL;
echo 'CHECK_P7_OPS_SITE_OPERATIONS_TABLE_DATA=OK' . PHP_EOL;
echo 'P7_OPS_SITE_OPERATIONS_UI_CORE_SMOKE_OK' . PHP_EOL;
