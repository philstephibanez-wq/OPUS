<?php
declare(strict_types=1);

echo 'P7_OPS_PROFILER_VISIBLE_MODE_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';

$files = [
    $publicDir . '/language.php',
    $publicDir . '/router.php',
    $publicDir . '/ops-ui.css',
    $root . '/sites/opus-p7-ops/README.md',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('VISIBLE_PROFILER_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('VISIBLE_PROFILER_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'P7_OPS_PROFILER_VISIBLE_MODE_CORE',
    'p7ops_profiler_visible_boot_once',
    'p7ops_profiler_visible_enabled',
    'p7ops_profiler_visible_badge_html',
    'p7ops_profiler_visible_apply_html',
    'p7ops_profiler_visible_boot_once();',
    'PROFILER ACTIVE',
    'opus-profiler-visible-active',
    'opus-profiler-visible-ribbon',
    'outline:6px solid #f59e0b',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('VISIBLE_PROFILER_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_VISIBLE_PROFILER_MARKERS=OK' . PHP_EOL;

require_once $publicDir . '/language.php';

$_GET = ['site' => 'site-alpha', 'lang' => 'fr', 'profiler' => '1'];
$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/operations?site=site-alpha&lang=fr&profiler=1';
$_SERVER['REQUEST_METHOD'] = 'GET';

$html = '<!doctype html><html><body><main>Test</main></body></html>';
$out = p7ops_profiler_visible_apply_html($html);

foreach ([
    'class="opus-profiler-visible-active"',
    'PROFILER ACTIVE',
    '/opus-lstsar-manager/profiler',
    '/opus-lstsar-manager/profiler/exit',
] as $marker) {
    if (!str_contains($out, $marker)) {
        throw new RuntimeException('VISIBLE_PROFILER_HTML_MARKER_MISSING: ' . $marker);
    }
}

if (!p7ops_profiler_visible_static_path('/favicon.ico') || !p7ops_profiler_visible_static_path('/ops-ui.css')) {
    throw new RuntimeException('VISIBLE_PROFILER_STATIC_PATH_CHECK_FAILED');
}

echo 'CHECK_P7_OPS_VISIBLE_PROFILER_HTML=OK' . PHP_EOL;
echo 'P7_OPS_PROFILER_VISIBLE_MODE_CORE_SMOKE_OK' . PHP_EOL;
