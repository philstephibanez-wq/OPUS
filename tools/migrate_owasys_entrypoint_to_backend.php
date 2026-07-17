<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
$entry = $site . '/www/index.php';
$bootstrap = $site . '/application/default/bootstrap.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if (!is_file($entry)) {
    $fail('OWASYS_ENTRYPOINT_MIGRATION_SOURCE_MISSING');
}
if (is_file($bootstrap)) {
    $fail('OWASYS_ENTRYPOINT_MIGRATION_BOOTSTRAP_ALREADY_EXISTS');
}

$source = file_get_contents($entry);
if (!is_string($source) || $source === '') {
    $fail('OWASYS_ENTRYPOINT_MIGRATION_SOURCE_READ_FAILED');
}

foreach ([
    '$siteRoot = dirname(__DIR__);',
    'use Opus\\Fsm\\FsmSiteLoader;',
    '<aside class="ow-sidebar">',
] as $requiredMarker) {
    if (!str_contains($source, $requiredMarker)) {
        $fail('OWASYS_ENTRYPOINT_MIGRATION_UNEXPECTED_SOURCE:' . $requiredMarker);
    }
}

$migrated = str_replace(
    '$siteRoot = dirname(__DIR__);',
    '$siteRoot = dirname(__DIR__, 2);',
    $source,
    $siteRootReplacementCount
);
if ($siteRootReplacementCount !== 1) {
    $fail('OWASYS_ENTRYPOINT_MIGRATION_SITE_ROOT_REPLACEMENT_INVALID');
}

$migrated = str_replace(
    " * OWASYS public entry.\n *\n * Standard OPUS site entry for the OWASYS application.\n * It renders data-only state view-models and drives navigation from OWASYS_NAVIGATION_FSM_V1.",
    " * OWASYS backend bootstrap.\n *\n * Application orchestration belongs under application/default.\n * The public www/index.php only delegates to this bootstrap.",
    $migrated
);

$entryContent = <<<'PHP'
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/application/default/bootstrap.php';
PHP;
$entryContent .= PHP_EOL;

$writeAtomic = static function (string $path, string $content): void {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('OWASYS_ENTRYPOINT_MIGRATION_DIRECTORY_CREATE_FAILED:' . $directory);
    }
    $temporary = $path . '.migration-' . bin2hex(random_bytes(8)) . '.tmp';
    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        throw new RuntimeException('OWASYS_ENTRYPOINT_MIGRATION_TEMP_WRITE_FAILED:' . $path);
    }
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('OWASYS_ENTRYPOINT_MIGRATION_ATOMIC_REPLACE_FAILED:' . $path);
    }
};

try {
    $writeAtomic($bootstrap, $migrated);
    $writeAtomic($entry, $entryContent);
} catch (Throwable $exception) {
    if (is_file($bootstrap)) {
        @unlink($bootstrap);
    }
    $writeAtomic($entry, $source);
    $fail($exception->getMessage());
}

$lint = static function (string $path) use ($fail): void {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $fail('OWASYS_ENTRYPOINT_MIGRATION_PHP_LINT_FAILED:' . $path . ':' . implode('|', $output));
    }
};

$lint($entry);
$lint($bootstrap);

fwrite(STDOUT, "OWASYS_ENTRYPOINT_MIGRATION_OK\n");
