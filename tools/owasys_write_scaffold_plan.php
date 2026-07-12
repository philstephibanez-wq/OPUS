<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationScaffoldWriter;

if (($argv[1] ?? '') === '' || in_array($argv[1] ?? '', ['-h', '--help'], true)) {
    echo "OWASYS scaffold writer\n";
    echo "Usage:\n";
    echo "  php tools/owasys_write_scaffold_plan.php PLAN_JSON --dry-run\n";
    echo "  php tools/owasys_write_scaffold_plan.php PLAN_JSON --write\n";
    echo "\n";
    echo "Default mode is dry-run. Actual write requires --write.\n";
    exit(($argv[1] ?? '') === '' ? 1 : 0);
}

$planFile = (string) $argv[1];
$args = array_slice($argv, 2);
$write = in_array('--write', $args, true);
$dryRun = !$write;

if (in_array('--write', $args, true) && in_array('--dry-run', $args, true)) {
    fwrite(STDERR, "OWASYS_SCAFFOLD_WRITE_MODE_CONFLICT\n");
    exit(1);
}

if (!is_file($planFile)) {
    fwrite(STDERR, "OWASYS_PLAN_FILE_NOT_FOUND: {$planFile}\n");
    exit(1);
}

$plan = json_decode((string) file_get_contents($planFile), true);
if (!is_array($plan)) {
    fwrite(STDERR, "OWASYS_PLAN_JSON_INVALID: {$planFile}\n");
    exit(1);
}

try {
    $summary = (new ApplicationScaffoldWriter(dirname(__DIR__)))->write($plan, $dryRun);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

if ($dryRun) {
    echo "OWASYS_SCAFFOLD_WRITE_DRY_RUN_OK: " . $summary['site_root'] . "\n";
    echo "OWASYS_SCAFFOLD_DIRECTORIES=" . $summary['directories'] . "\n";
    echo "OWASYS_SCAFFOLD_FILES=" . $summary['files'] . "\n";
    exit(0);
}

echo "OWASYS_SCAFFOLD_WRITE_OK: " . $summary['site_root'] . "\n";
echo "OWASYS_SCAFFOLD_DIRECTORIES=" . $summary['directories'] . "\n";
echo "OWASYS_SCAFFOLD_FILES=" . $summary['files'] . "\n";
