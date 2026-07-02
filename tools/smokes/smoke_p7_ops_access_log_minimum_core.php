<?php
declare(strict_types=1);

echo 'P7_OPS_ACCESS_LOG_MINIMUM_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$languageFile = $root . '/sites/opus-p7-ops/public/language.php';
$routerFile = $root . '/sites/opus-p7-ops/public/router.php';
$readmeFile = $root . '/sites/opus-p7-ops/README.md';

foreach ([$languageFile, $routerFile, $readmeFile] as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('ACCESS_LOG_FILE_MISSING: ' . $file);
    }
}

$language = file_get_contents($languageFile);
$router = file_get_contents($routerFile);
$readme = file_get_contents($readmeFile);

if (!is_string($language) || !is_string($router) || !is_string($readme)) {
    throw new RuntimeException('ACCESS_LOG_READ_FAILED');
}

foreach ([
    'P7_OPS_ACCESS_LOG_MINIMUM_CORE',
    'p7ops_access_log_once',
    'p7ops_log_line',
    'access.log',
    'http_request',
    'REQUEST_URI',
] as $marker) {
    if (!str_contains($language . $router . $readme, $marker)) {
        throw new RuntimeException('ACCESS_LOG_MARKER_MISSING: ' . $marker);
    }
}

if (!str_contains($router, 'p7ops_access_log_once();')) {
    throw new RuntimeException('ACCESS_LOG_ROUTER_CALL_MISSING');
}

echo 'CHECK_P7_OPS_ACCESS_LOG_MARKERS=OK' . PHP_EOL;

require_once $languageFile;

$logDir = $root . '/var/logs/opus_lstsar-manager';
$logFile = $logDir . '/access.log';

if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    throw new RuntimeException('ACCESS_LOG_TEST_DIR_CREATE_FAILED');
}

$before = is_file($logFile) ? (int) filesize($logFile) : 0;

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/english/operations?site=site-alpha&lang=en&smoke=p7ops_access_log';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'P7_OPS_ACCESS_LOG_MINIMUM_CORE_SMOKE';

p7ops_access_log_once();

if (!is_file($logFile)) {
    throw new RuntimeException('ACCESS_LOG_NOT_CREATED');
}

$after = (int) filesize($logFile);
if ($after <= $before) {
    throw new RuntimeException('ACCESS_LOG_NOT_APPENDED');
}

$tail = file_get_contents($logFile, false, null, max(0, $after - 4096));
if (!is_string($tail)) {
    throw new RuntimeException('ACCESS_LOG_TAIL_READ_FAILED');
}

foreach ([
    '"event":"http_request"',
    '"method":"GET"',
    '"uri":"/english/operations?site=site-alpha&lang=en&smoke=p7ops_access_log"',
    '"path":"/english/operations"',
    '"query":"site=site-alpha&lang=en&smoke=p7ops_access_log"',
    '"remote_addr":"127.0.0.1"',
] as $marker) {
    if (!str_contains($tail, $marker)) {
        throw new RuntimeException('ACCESS_LOG_LINE_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_ACCESS_LOG_APPEND=OK' . PHP_EOL;
echo 'P7_OPS_ACCESS_LOG_MINIMUM_CORE_SMOKE_OK' . PHP_EOL;
