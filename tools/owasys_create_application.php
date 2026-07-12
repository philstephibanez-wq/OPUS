<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationCreator;

if (($argv[1] ?? '') === '' || in_array($argv[1] ?? '', ['-h', '--help'], true)) {
    echo "OWASYS application creator\n";
    echo "Usage:\n";
    echo "  php tools/owasys_create_application.php REQUEST_JSON --dry-run\n";
    echo "  php tools/owasys_create_application.php REQUEST_JSON --write --validate\n";
    echo "\n";
    echo "Default mode is dry-run. Actual creation requires --write.\n";
    exit(($argv[1] ?? '') === '' ? 1 : 0);
}

$requestFile = (string) $argv[1];
$args = array_slice($argv, 2);
$write = in_array('--write', $args, true);
$dryRun = in_array('--dry-run', $args, true);
$validate = in_array('--validate', $args, true);

if ($write && $dryRun) {
    fwrite(STDERR, "OWASYS_APPLICATION_CREATE_MODE_CONFLICT\n");
    exit(1);
}

if (!is_file($requestFile)) {
    fwrite(STDERR, "OWASYS_APPLICATION_CREATE_REQUEST_NOT_FOUND: {$requestFile}\n");
    exit(1);
}

$request = json_decode((string) file_get_contents($requestFile), true);
if (!is_array($request)) {
    fwrite(STDERR, "OWASYS_APPLICATION_CREATE_REQUEST_JSON_INVALID: {$requestFile}\n");
    exit(1);
}

try {
    $result = (new ApplicationCreator(dirname(__DIR__)))->create($request, $write, $validate);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$siteRoot = (string) ($result['site_root'] ?? '');
$dryRunSummary = is_array($result['dry_run'] ?? null) ? $result['dry_run'] : [];

if (!$write) {
    echo "OWASYS_APPLICATION_CREATE_DRY_RUN_OK: {$siteRoot}\n";
    echo "OWASYS_APPLICATION_CREATE_DIRECTORIES=" . (int) ($dryRunSummary['directories'] ?? 0) . "\n";
    echo "OWASYS_APPLICATION_CREATE_FILES=" . (int) ($dryRunSummary['files'] ?? 0) . "\n";
    exit(0);
}

$writeSummary = is_array($result['write'] ?? null) ? $result['write'] : [];
$validation = is_array($result['validation'] ?? null) ? $result['validation'] : [];
$manifest = is_array($result['manifest'] ?? null) ? $result['manifest'] : [];

echo "OWASYS_APPLICATION_CREATE_WRITE_OK: {$siteRoot}\n";
echo "OWASYS_APPLICATION_CREATE_DIRECTORIES=" . (int) ($writeSummary['directories'] ?? 0) . "\n";
echo "OWASYS_APPLICATION_CREATE_FILES=" . (int) ($writeSummary['files'] ?? 0) . "\n";

if (($validation['status'] ?? null) === 'ok') {
    echo "OWASYS_APPLICATION_CREATE_VALIDATE_OK: " . (string) ($validation['site_id'] ?? '') . "\n";
}

if (isset($manifest['path'])) {
    echo "OWASYS_APPLICATION_CREATE_MANIFEST=" . (string) $manifest['path'] . "\n";
}
