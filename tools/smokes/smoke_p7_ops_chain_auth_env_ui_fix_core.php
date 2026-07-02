<?php
declare(strict_types=1);

echo 'P7_OPS_CHAIN_AUTH_ENV_UI_FIX_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';
$configDir = $root . '/sites/opus-p7-ops/config';
$logDir = $root . '/var/logs/opus_lstsar-manager';

$files = [
    $publicDir . '/language.php',
    $publicDir . '/router.php',
    $publicDir . '/ops-ui.css',
    $configDir . '/environment.prod.example.php',
    $root . '/sites/opus-p7-ops/README.md',
    $root . '/var/logs/.gitignore',
    $logDir . '/.gitkeep',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('UI_FIX_FILE_MISSING: ' . $file);
    }
}

$language = file_get_contents($publicDir . '/language.php');
$css = file_get_contents($publicDir . '/ops-ui.css');
$prod = file_get_contents($configDir . '/environment.prod.example.php');
$readme = file_get_contents($root . '/sites/opus-p7-ops/README.md');

if (!is_string($language) || !is_string($css) || !is_string($prod) || !is_string($readme)) {
    throw new RuntimeException('UI_FIX_READ_FAILED');
}

foreach ([
    'P7_OPS_CHAIN_AUTH_ENV_CORE',
    'p7ops_profiler_start_microtime',
    'microtime(true)',
] as $marker) {
    if (!str_contains($language, $marker)) {
        throw new RuntimeException('UI_FIX_LANGUAGE_MARKER_MISSING: ' . $marker);
    }
}

foreach ([
    'environment.prod.example.php',
    'CHANGE_ME_WITH_PASSWORD_HASH',
] as $marker) {
    if (!str_contains($prod, $marker)) {
        throw new RuntimeException('UI_FIX_PROD_MARKER_MISSING: ' . $marker);
    }
}

foreach ([
    'P7_OPS_HEADER_NO_OVERLAP_CORE',
    'position:static!important',
    'flex-wrap:wrap!important',
    'overflow-x:hidden',
    '@media (max-width:1100px)',
] as $marker) {
    if (!str_contains($css, $marker)) {
        throw new RuntimeException('UI_FIX_CSS_MARKER_MISSING: ' . $marker);
    }
}

foreach ([
    'P7_OPS_CHAIN_AUTH_ENV_UI_FIX_CORE',
    'header navigation and language selector overlap',
] as $marker) {
    if (!str_contains($readme, $marker)) {
        throw new RuntimeException('UI_FIX_README_MARKER_MISSING: ' . $marker);
    }
}

if (str_contains($language, 'hrtime(true)')) {
    throw new RuntimeException('UI_FIX_HRTIME_STILL_PRESENT_IN_LANGUAGE');
}

echo 'CHECK_P7_OPS_CHAIN_AUTH_ENV_UI_FIX_MARKERS=OK' . PHP_EOL;

require_once $publicDir . '/language.php';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/operations?site=site-alpha&lang=fr&profiler=1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'P7_OPS_CHAIN_AUTH_ENV_UI_FIX_CORE_SMOKE';
$_GET = ['site' => 'site-alpha', 'lang' => 'fr', 'profiler' => '1'];

$access = $logDir . '/access.log';
$profiler = $logDir . '/profiler.log';
$beforeAccess = is_file($access) ? (int) filesize($access) : 0;
$beforeProfiler = is_file($profiler) ? (int) filesize($profiler) : 0;

p7ops_access_log_once();
p7ops_profiler_start_once();
usleep(1000);
p7ops_profiler_finish_once('smoke');

if (!is_file($access) || (int) filesize($access) <= $beforeAccess) {
    throw new RuntimeException('UI_FIX_ACCESS_LOG_NOT_APPENDED');
}

if (!is_file($profiler) || (int) filesize($profiler) <= $beforeProfiler) {
    throw new RuntimeException('UI_FIX_PROFILER_LOG_NOT_APPENDED');
}

echo 'CHECK_P7_OPS_CHAIN_AUTH_ENV_UI_FIX_LOGS=OK' . PHP_EOL;
echo 'P7_OPS_CHAIN_AUTH_ENV_UI_FIX_CORE_SMOKE_OK' . PHP_EOL;
