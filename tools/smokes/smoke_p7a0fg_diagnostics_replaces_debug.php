<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "CHECK_COMPOSER_AUTOLOAD=FAIL\n");
    exit(1);
}

require $autoload;

use Opus\Diagnostics\Diagnostics;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;

echo "P7A0FG_DIAGNOSTICS_REPLACES_DEBUG_SMOKE\n";

$logDir = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'p7a0fg_diagnostics_log';
$profilerDir = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'profiler' . DIRECTORY_SEPARATOR . 'p7a0fg_diagnostics';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'opus.log';

foreach (glob($profilerDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $traceFile) {
    unlink($traceFile);
}
if (is_file($logFile)) {
    unlink($logFile);
}
if (is_dir($logDir)) {
    rmdir($logDir);
}
if (is_dir($profilerDir)) {
    rmdir($profilerDir);
}

$profiler = new Profiler($profilerDir);
$profiler->start('TRACE_P7A0FG_DIAGNOSTICS');

Diagnostics::configure(true, $logDir);
Diagnostics::configureProfiler($profiler);
Diagnostics::configureLogger(new Logger($logDir), 'TRACE_P7A0FG_DIAGNOSTICS', 'diagnostics');
Diagnostics::debug('diagnostics message', __FILE__, __LINE__, 'cyan');
Diagnostics::dump('diagnostics dump', ['visible' => 'ok', 'password' => 'secret'], __FILE__, __LINE__, 'red');

$html = Diagnostics::renderLegacyHtml();
if (!str_contains($html, 'diagnostics message') || !str_contains($html, 'diagnostics dump')) {
    fwrite(STDERR, "CHECK_DIAGNOSTICS_HTML_OUTPUT=FAIL\n");
    exit(1);
}
echo "CHECK_DIAGNOSTICS_HTML_OUTPUT=OK\n";

$tracePath = $profiler->stop(['status' => 200]);
Diagnostics::clear();

$logContents = is_file($logFile) ? file_get_contents($logFile) : false;
if ($logContents === false || !str_contains($logContents, '"channel":"diagnostics"') || !str_contains($logContents, '"trace_id":"TRACE_P7A0FG_DIAGNOSTICS"')) {
    fwrite(STDERR, "CHECK_DIAGNOSTICS_LOG=FAIL\n");
    exit(1);
}
echo "CHECK_DIAGNOSTICS_LOG=OK\n";

$traceContents = is_file($tracePath) ? file_get_contents($tracePath) : false;
if ($traceContents === false || !str_contains($traceContents, '"name": "debug.message"') || !str_contains($traceContents, '"name": "debug.dump"')) {
    fwrite(STDERR, "CHECK_DIAGNOSTICS_PROFILER=FAIL\n");
    exit(1);
}
echo "CHECK_DIAGNOSTICS_PROFILER=OK\n";

unlink($logFile);
rmdir($logDir);
unlink($tracePath);
rmdir($profilerDir);

echo "P7A0FG_DIAGNOSTICS_REPLACES_DEBUG_SMOKE_OK\n";
