<?php
declare(strict_types=1);

echo "P7_OPS_SITE_DEPLOYMENT_CORE_SMOKE" . PHP_EOL;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

foreach ([
    'RUN_P7_OPS_SITE.cmd',
    'sites/opus-p7-ops/public/router.php',
    'sites/opus-p7-ops/public/index.php',
] as $file) {
    if (!is_file($root . '/' . $file)) {
        throw new RuntimeException('OPS_FILE_MISSING: ' . $file);
    }
}

echo "CHECK_P7_OPS_SITE_FILES=OK" . PHP_EOL;

$index = file_get_contents($root . '/sites/opus-p7-ops/public/index.php');
if ($index === false || !str_contains($index, 'rawPath = parse_url') || !str_contains($index, 'rtrim(')) {
    throw new RuntimeException('OPS_INDEX_NORMALIZATION_INVALID');
}

echo "CHECK_P7_OPS_SITE_ROUTE_NORMALIZATION=OK" . PHP_EOL;

$factory = new \OpusLstsarManager\View\LstsarManagerViewModelFactory();
$controller = new \OpusLstsarManager\Controller\OperationsController($factory);
$vm = $controller->operations('site-alpha');

if (!is_array($vm) || !is_array($vm['operations_dashboard'] ?? null)) {
    throw new RuntimeException('OPS_OPERATIONS_VM_INVALID');
}

echo "CHECK_P7_OPS_SITE_OPERATIONS_VM=OK" . PHP_EOL;
echo "P7_OPS_SITE_DEPLOYMENT_CORE_SMOKE_OK" . PHP_EOL;
