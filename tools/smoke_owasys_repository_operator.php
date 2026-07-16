<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\RepositoryOperator;

$root = dirname(__DIR__);
$temporaryRoot = $root . '/var/smoke/owasys-repository-operator';
$applicationRoot = $temporaryRoot . '/sites/demo';

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($child) ? $removeTree($child) : @unlink($child);
    }
    @rmdir($path);
};

$run = static function (string $directory, array $arguments): void {
    $command = 'git -C ' . escapeshellarg($directory);
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }
    exec($command . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_REPOSITORY_OPERATOR_SETUP_FAILED\n");
        exit(1);
    }
};

$removeTree($temporaryRoot);
mkdir($applicationRoot . '/config', 0775, true);
file_put_contents($applicationRoot . '/config/site.json', "{\n  \"name\": \"demo\"\n}\n");
$run($temporaryRoot, ['init']);
$run($temporaryRoot, ['config', 'user.name', 'OWASYS Smoke']);
$run($temporaryRoot, ['config', 'user.email', 'owasys-smoke@example.invalid']);
$run($temporaryRoot, ['add', '.']);
$run($temporaryRoot, ['commit', '-m', 'Initial smoke repository']);

file_put_contents($applicationRoot . '/config/site.json', "{\n  \"name\": \"demo updated\"\n}\n");
$relativeApplication = str_replace('\\', '/', substr($applicationRoot, strlen($root) + 1));
$operator = new RepositoryOperator($root);

$stage = $operator->stageApplication($relativeApplication);
if (($stage['contract'] ?? null) !== RepositoryOperator::CONTRACT || ($stage['arbitrary_command'] ?? true) !== false) {
    fwrite(STDERR, "OWASYS_REPOSITORY_OPERATOR_STAGE_INVALID\n");
    exit(1);
}
if (!in_array('sites/demo/config/site.json', $stage['staged_files'] ?? [], true)) {
    fwrite(STDERR, "OWASYS_REPOSITORY_OPERATOR_STAGE_SCOPE_INVALID\n");
    exit(1);
}

$commit = $operator->commitApplication($relativeApplication, 'Update demo site');
if (($commit['contract'] ?? null) !== RepositoryOperator::CONTRACT || ($commit['push_performed'] ?? true) !== false) {
    fwrite(STDERR, "OWASYS_REPOSITORY_OPERATOR_COMMIT_INVALID\n");
    exit(1);
}
if (preg_match('/^[a-f0-9]{40}$/', (string) ($commit['commit'] ?? '')) !== 1) {
    fwrite(STDERR, "OWASYS_REPOSITORY_OPERATOR_COMMIT_HASH_INVALID\n");
    exit(1);
}

$invalidMessageRejected = false;
try {
    $operator->commitApplication($relativeApplication, "bad\nmessage");
} catch (RuntimeException $exception) {
    $invalidMessageRejected = $exception->getMessage() === 'OWASYS_GIT_COMMIT_MESSAGE_INVALID';
}
if (!$invalidMessageRejected) {
    fwrite(STDERR, "OWASYS_REPOSITORY_OPERATOR_MESSAGE_GUARD_MISSING\n");
    exit(1);
}

$removeTree($temporaryRoot);
echo "OWASYS_REPOSITORY_OPERATOR_SMOKE_OK\n";
