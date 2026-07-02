<?php
declare(strict_types=1);

echo 'P7_OPS_PROFILER_EXIT_FIX_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';

$files = [
    $publicDir . '/language.php',
    $publicDir . '/router.php',
    $publicDir . '/profiler-exit.php',
    $root . '/sites/opus-p7-ops/README.md',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('PROFILER_EXIT_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('PROFILER_EXIT_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'P7_OPS_PROFILER_EXIT_FIX_CORE',
    'p7ops_profiler_url_without_profiler',
    'p7ops_profiler_disable_all_modes',
    'p7ops_profiler_exit_path',
    'p7ops_clean_profiler_enabled',
    'p7ops_sf_profiler_enabled',
    'p7ops_profiler_enabled',
    'p7ops_profiler_url_without_profiler((string) ($_GET',
    'Cache-Control: no-store',
    '!p7ops_profiler_exit_path($path)',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('PROFILER_EXIT_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_PROFILER_EXIT_MARKERS=OK' . PHP_EOL;

require_once $publicDir . '/language.php';

$cases = [
    ['/opus-lstsar-manager/operations?site=site-alpha&lang=fr&profiler=1', '/opus-lstsar-manager/operations?site=site-alpha&lang=fr'],
    ['/opus-lstsar-manager?profiler=1&site=site-alpha&lang=en', '/opus-lstsar-manager?site=site-alpha&lang=en'],
    ['/opus-lstsar-manager/profiler?token=abc&profile=1&_profiler=1', '/opus-lstsar-manager/profiler?token=abc'],
    ['', '/opus-lstsar-manager'],
];

foreach ($cases as [$input, $expected]) {
    $actual = p7ops_profiler_url_without_profiler($input);
    if ($actual !== $expected) {
        throw new RuntimeException('PROFILER_EXIT_SANITIZE_FAILED: ' . $input . ' => ' . $actual . ' expected ' . $expected);
    }
}

foreach ([
    '/opus-lstsar-manager/profiler/exit' => true,
    '/_profiler/exit' => true,
    '/opus-lstsar-manager/profiler' => false,
] as $path => $expected) {
    if (p7ops_profiler_exit_path($path) !== $expected) {
        throw new RuntimeException('PROFILER_EXIT_PATH_CHECK_FAILED: ' . $path);
    }
}

echo 'CHECK_P7_OPS_PROFILER_EXIT_SANITIZE=OK' . PHP_EOL;
echo 'P7_OPS_PROFILER_EXIT_FIX_CORE_SMOKE_OK' . PHP_EOL;
