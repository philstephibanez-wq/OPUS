<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "CHECK_COMPOSER_AUTOLOAD=FAIL\n");
    exit(1);
}

require $autoload;

use Opus\Profiler\Profiler;

echo "P7A0B_PROFILER_TRACE_FOUNDATION_SMOKE\n";

$storageDir = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'profiler' . DIRECTORY_SEPARATOR . 'p7a0b_smoke';

if (is_dir($storageDir)) {
    foreach (glob($storageDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        unlink($file);
    }
    rmdir($storageDir);
}

$profiler = new Profiler($storageDir);
$trace = $profiler->start('TRACE_P7A0B_SMOKE');
$profiler->event('request', 'request.received', ['path' => '/']);
$profiler->event('route', 'route.matched', ['route_id' => 'home.index']);
$profiler->event('fsm', 'transition.accepted', ['state' => 'home.index']);
$profiler->event('db', 'query.planned', ['provider' => 'sqlite', 'password' => 'secret']);
$profiler->event('template', 'template.rendered', ['template' => 'home.score']);

$path = $profiler->stop(['status' => 200, 'token' => 'secret']);

if (!is_file($path)) {
    fwrite(STDERR, "CHECK_TRACE_FILE_CREATED=FAIL\n");
    exit(1);
}

$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "CHECK_TRACE_FILE_READ=FAIL\n");
    exit(1);
}

$data = json_decode($contents, true);
if (!is_array($data)) {
    fwrite(STDERR, "CHECK_TRACE_JSON=FAIL\n");
    exit(1);
}

if (($data['schema'] ?? '') !== 'OPUS_PROFILER_TRACE_V1') {
    fwrite(STDERR, "CHECK_TRACE_SCHEMA=FAIL\n");
    exit(1);
}

if (($data['trace_id'] ?? '') !== 'TRACE_P7A0B_SMOKE') {
    fwrite(STDERR, "CHECK_TRACE_ID=FAIL\n");
    exit(1);
}

if (($data['event_count'] ?? 0) < 7) {
    fwrite(STDERR, "CHECK_TRACE_EVENTS=FAIL\n");
    exit(1);
}

if (!str_contains($contents, '"password": "[REDACTED]"') || !str_contains($contents, '"token": "[REDACTED]"')) {
    fwrite(STDERR, "CHECK_TRACE_SECRET_REDACTION=FAIL\n");
    exit(1);
}

unlink($path);
rmdir($storageDir);

echo "CHECK_TRACE_FILE_CREATED=OK\n";
echo "CHECK_TRACE_SCHEMA=OK\n";
echo "CHECK_TRACE_ID=OK\n";
echo "CHECK_TRACE_EVENTS=OK\n";
echo "CHECK_TRACE_SECRET_REDACTION=OK\n";
echo "P7A0B_PROFILER_TRACE_FOUNDATION_SMOKE_OK\n";
