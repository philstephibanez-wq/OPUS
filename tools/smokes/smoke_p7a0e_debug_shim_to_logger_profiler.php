<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "CHECK_COMPOSER_AUTOLOAD=FAIL\n");
    exit(1);
}

require $autoload;

if (!class_exists('OPUS_Debug', false)) {
    require $root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Debug' . DIRECTORY_SEPARATOR . 'Debug.class.php';
}

use Opus\Log\Logger;
use Opus\Profiler\Profiler;

echo "P7A0E_DEBUG_SHIM_TO_LOGGER_PROFILER_SMOKE\n";

$logDir = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'p7a0e_debug_shim_log';
$profilerDir = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'profiler' . DIRECTORY_SEPARATOR . 'p7a0e_debug_shim';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'opus.log';

foreach ([$logFile] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
foreach (glob($profilerDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $traceFile) {
    unlink($traceFile);
}
if (is_dir($logDir)) {
    rmdir($logDir);
}
if (is_dir($profilerDir)) {
    rmdir($profilerDir);
}

$logger = new Logger($logDir);
$profiler = new Profiler($profilerDir);
$profiler->start('TRACE_P7A0E_DEBUG_SHIM');

OPUS_Debug::setDebug(true, $logDir);
OPUS_Debug::setLogger($logger, 'TRACE_P7A0E_DEBUG_SHIM', 'legacy_debug');
OPUS_Debug::setProfiler($profiler);
OPUS_Debug::add('legacy message', __FILE__, __LINE__, 'cyan');
OPUS_Debug::addDump('legacy dump', ['visible' => 'ok', 'password' => 'secret'], __FILE__, __LINE__, 'red');

$html = OPUS_Debug::get();
if (!str_contains($html, 'legacy message') || !str_contains($html, 'legacy dump')) {
    fwrite(STDERR, "CHECK_LEGACY_HTML_OUTPUT=FAIL\n");
    exit(1);
}
echo "CHECK_LEGACY_HTML_OUTPUT=OK\n";

$tracePath = $profiler->stop(['status' => 200]);
OPUS_Debug::clearBridge();

if (!is_file($logFile)) {
    fwrite(STDERR, "CHECK_LOG_BRIDGE_FILE=FAIL\n");
    exit(1);
}
$logContents = file_get_contents($logFile);
if ($logContents === false || !str_contains($logContents, '"channel":"legacy_debug"') || !str_contains($logContents, '"trace_id":"TRACE_P7A0E_DEBUG_SHIM"')) {
    fwrite(STDERR, "CHECK_LOG_BRIDGE_CONTENT=FAIL\n");
    exit(1);
}
echo "CHECK_LOG_BRIDGE=OK\n";

if (!is_file($tracePath)) {
    fwrite(STDERR, "CHECK_PROFILER_BRIDGE_FILE=FAIL\n");
    exit(1);
}
$traceContents = file_get_contents($tracePath);
if ($traceContents === false) {
    fwrite(STDERR, "CHECK_PROFILER_BRIDGE_READ=FAIL\n");
    exit(1);
}
$trace = json_decode($traceContents, true);
if (!is_array($trace)) {
    fwrite(STDERR, "CHECK_PROFILER_BRIDGE_JSON=FAIL\n");
    exit(1);
}

$eventNames = [];
foreach (($trace['events'] ?? []) as $event) {
    if (is_array($event)) {
        $eventNames[] = (string) ($event['name'] ?? '');
    }
}

if (!in_array('debug.add', $eventNames, true) || !in_array('debug.dump', $eventNames, true)) {
    fwrite(STDERR, "CHECK_PROFILER_BRIDGE_EVENTS=FAIL\n");
    exit(1);
}
echo "CHECK_PROFILER_BRIDGE=OK\n";

unlink($logFile);
rmdir($logDir);
unlink($tracePath);
rmdir($profilerDir);

echo "P7A0E_DEBUG_SHIM_TO_LOGGER_PROFILER_SMOKE_OK\n";
