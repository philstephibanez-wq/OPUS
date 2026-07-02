<?php
declare(strict_types=1);

$lines = ['P7_OPS_SITE_HEALTH_HUB_CORE_SMOKE'];
$root = dirname(__DIR__, 2);
$smokePaths = [
    'index' => $root . '/sites/opus-p7-ops/public/index.php',
    'router' => $root . '/sites/opus-p7-ops/public/router.php',
    'action' => $root . '/sites/opus-p7-ops/public/action.php',
    'command' => $root . '/sites/opus-p7-ops/public/command.php',
    'navigation' => $root . '/sites/opus-p7-ops/public/navigation.php',
    'diagnostics' => $root . '/sites/opus-p7-ops/public/diagnostics.php',
    'health' => $root . '/sites/opus-p7-ops/public/health.php',
    'css' => $root . '/sites/opus-p7-ops/public/ops-ui.css',
    'readme' => $root . '/sites/opus-p7-ops/README.md',
];

$combined = '';
foreach ($smokePaths as $label => $file) {
    if (!is_file($file)) {
        throw new RuntimeException('HEALTH_HUB_FILE_MISSING: ' . $label . '=' . $file);
    }

    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('HEALTH_HUB_READ_FAILED: ' . $label);
    }

    $combined .= $source . PHP_EOL;
}

foreach ([
    'P7_OPS_SITE_HEALTH_HUB_CORE',
    'OPUS OPS Site Health Hub',
    'Route matrix',
    'Public file matrix',
    'Regression smoke matrix',
    'Health payload',
    'side_effects',
    '/opus-lstsar-manager/health',
    '/opus-lstsar-manager/health-hub',
    'smoke_p7_ops_runtime_diagnostics_core.php',
    'smoke_p7_ops_site_health_hub_core.php',
    'Dashboard',
    'Operations',
    'Command Center',
    'Navigation',
    'Diagnostics',
    'Health Hub',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('HEALTH_HUB_MARKER_MISSING: ' . $marker);
    }
}

$lines[] = 'CHECK_P7_OPS_SITE_HEALTH_HUB_MARKERS=OK';

$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/health?site=site-alpha';
$_GET = ['site' => 'site-alpha'];

$healthFile = $smokePaths['health'];
$routerFile = $smokePaths['router'];

ob_start();
require $healthFile;
$html = (string) ob_get_clean();
http_response_code(200);

foreach ([
    'OPUS OPS Site Health Hub',
    'P7_OPS_SITE_HEALTH_HUB_CORE',
    'Public files',
    'Expected smokes',
    'Operations view-model',
    'Route matrix',
    'Public file matrix',
    'Regression smoke matrix',
    'Health payload',
    'side_effects',
    'false',
    'site-alpha',
] as $marker) {
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('HEALTH_HUB_RENDER_MARKER_MISSING: ' . $marker);
    }
}

$lines[] = 'CHECK_P7_OPS_SITE_HEALTH_HUB_RENDER=OK';

$router = file_get_contents($routerFile);
if ($router === false) {
    throw new RuntimeException('HEALTH_HUB_ROUTER_READ_FAILED');
}

foreach ([
    '/opus-lstsar-manager/health',
    '/opus-lstsar-manager/health-hub',
    'health.php',
] as $marker) {
    if (!str_contains($router, $marker)) {
        throw new RuntimeException('HEALTH_HUB_ROUTER_MARKER_MISSING: ' . $marker);
    }
}

$lines[] = 'CHECK_P7_OPS_SITE_HEALTH_HUB_ROUTER=OK';
$lines[] = 'P7_OPS_SITE_HEALTH_HUB_CORE_SMOKE_OK';

echo implode(PHP_EOL, $lines) . PHP_EOL;
