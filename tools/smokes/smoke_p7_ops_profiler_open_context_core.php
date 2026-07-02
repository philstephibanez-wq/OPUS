<?php
declare(strict_types=1);

echo 'P7_OPS_PROFILER_OPEN_CONTEXT_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';

$files = [
    $publicDir . '/language.php',
    $publicDir . '/router.php',
    $publicDir . '/profiler.php',
    $root . '/sites/opus-p7-ops/README.md',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPEN_CONTEXT_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('OPEN_CONTEXT_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'P7_OPS_PROFILER_OPEN_CONTEXT_CORE',
    'p7ops_profiler_context_store_app_uri',
    'p7ops_profiler_context_last_app_uri',
    'p7ops_profiler_context_is_profiler_page',
    'Back to app',
    'Refresh profiler',
    'p7ops_profiler_context_store_app_uri();',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('OPEN_CONTEXT_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_PROFILER_OPEN_CONTEXT_MARKERS=OK' . PHP_EOL;

require_once $publicDir . '/language.php';

$_GET = ['site' => 'site-alpha', 'lang' => 'fr', 'profiler' => '1'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/profiler';
$GLOBALS['p7ops_visible_profiler_started_at'] = microtime(true);

$profilerRibbon = p7ops_profiler_visible_badge_html();
foreach ([
    'PROFILER ACTIVE',
    'Back to app',
    '/opus-lstsar-manager/operations',
    'Exit',
] as $marker) {
    if (!str_contains($profilerRibbon, $marker)) {
        throw new RuntimeException('OPEN_CONTEXT_PROFILER_RIBBON_MARKER_MISSING: ' . $marker);
    }
}

$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/operations?site=site-alpha&lang=fr&profiler=1';
$appRibbon = p7ops_profiler_visible_badge_html();
foreach ([
    'PROFILER ACTIVE',
    'Open profiler',
    '/opus-lstsar-manager/profiler',
    'Exit',
] as $marker) {
    if (!str_contains($appRibbon, $marker)) {
        throw new RuntimeException('OPEN_CONTEXT_APP_RIBBON_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_PROFILER_OPEN_CONTEXT_RIBBON=OK' . PHP_EOL;
echo 'P7_OPS_PROFILER_OPEN_CONTEXT_CORE_SMOKE_OK' . PHP_EOL;
