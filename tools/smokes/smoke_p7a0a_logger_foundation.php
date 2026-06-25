<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "CHECK_COMPOSER_AUTOLOAD=FAIL\n");
    exit(1);
}

require $autoload;

use Opus\Log\Logger;

echo "P7A0A_LOGGER_FOUNDATION_SMOKE\n";

$logDir = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'p7a0a_logger_smoke';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'opus.log';

if (is_file($logFile)) {
    unlink($logFile);
}

$logger = new Logger($logDir);
$logger->info('runtime', 'Logger smoke info', ['password' => 'secret', 'visible' => 'ok'], 'TRACE_SMOKE');
$logger->warning('db', 'Logger smoke warning', ['provider' => 'sqlite'], 'TRACE_SMOKE');

if (!is_file($logFile)) {
    fwrite(STDERR, "CHECK_LOG_FILE_CREATED=FAIL\n");
    exit(1);
}

$contents = file_get_contents($logFile);
if ($contents === false) {
    fwrite(STDERR, "CHECK_LOG_FILE_READ=FAIL\n");
    exit(1);
}

if (substr_count(trim($contents), PHP_EOL) !== 1) {
    fwrite(STDERR, "CHECK_LOG_LINE_COUNT=FAIL\n");
    exit(1);
}

if (!str_contains($contents, '"channel":"runtime"') || !str_contains($contents, '"channel":"db"')) {
    fwrite(STDERR, "CHECK_LOG_CHANNELS=FAIL\n");
    exit(1);
}

if (!str_contains($contents, '"password":"[REDACTED]"')) {
    fwrite(STDERR, "CHECK_SECRET_REDACTION=FAIL\n");
    exit(1);
}

unlink($logFile);
rmdir($logDir);

echo "CHECK_LOG_FILE_CREATED=OK\n";
echo "CHECK_LOG_CHANNELS=OK\n";
echo "CHECK_SECRET_REDACTION=OK\n";
echo "P7A0A_LOGGER_FOUNDATION_SMOKE_OK\n";
