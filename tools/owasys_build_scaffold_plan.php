<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ScaffoldPlanBuilder;

if (($argv[1] ?? '') === '' || in_array($argv[1] ?? '', ['-h', '--help'], true)) {
    echo "OWASYS scaffold plan builder\n";
    echo "Usage:\n";
    echo "  php tools/owasys_build_scaffold_plan.php REQUEST_JSON [OUTPUT_JSON]\n";
    echo "\n";
    echo "This tool is plan-only: it writes no generated site files.\n";
    exit(($argv[1] ?? '') === '' ? 1 : 0);
}

$requestFile = (string) $argv[1];
$outputFile = (string) ($argv[2] ?? '');

if (!is_file($requestFile)) {
    fwrite(STDERR, "OWASYS_REQUEST_FILE_NOT_FOUND: {$requestFile}\n");
    exit(1);
}

$request = json_decode((string) file_get_contents($requestFile), true);
if (!is_array($request)) {
    fwrite(STDERR, "OWASYS_REQUEST_JSON_INVALID: {$requestFile}\n");
    exit(1);
}

try {
    $plan = (new ScaffoldPlanBuilder())->build($request);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$json = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($json)) {
    fwrite(STDERR, "OWASYS_PLAN_JSON_ENCODE_FAILED\n");
    exit(1);
}
$json .= "\n";

if ($outputFile === '') {
    echo $json;
    exit(0);
}

$dir = dirname($outputFile);
if ($dir !== '' && $dir !== '.' && !is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
    fwrite(STDERR, "OWASYS_PLAN_OUTPUT_DIR_CREATE_FAILED: {$dir}\n");
    exit(1);
}

if (file_put_contents($outputFile, $json) === false) {
    fwrite(STDERR, "OWASYS_PLAN_OUTPUT_WRITE_FAILED: {$outputFile}\n");
    exit(1);
}

echo "OWASYS_SCAFFOLD_PLAN_WRITTEN: {$outputFile}\n";
