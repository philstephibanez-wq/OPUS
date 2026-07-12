<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteId = 'owasys-bin-opus-smoke-demo';
$siteRoot = 'sites/' . $siteId;
$absoluteRoot = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $siteRoot);
$requestFile = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'owasys-bin-opus-smoke-request.json';

/**
 * Removes a directory tree created by this smoke test.
 */
function owasys_bin_opus_remove_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $itemPath = $item->getPathname();
        if ($item->isDir()) {
            @rmdir($itemPath);
        } else {
            @unlink($itemPath);
        }
    }

    @rmdir($path);
}

/**
 * Executes a command and returns combined output.
 *
 * @return array{code:int,output:string}
 */
function owasys_bin_opus_exec(string $command): array
{
    $lines = [];
    exec($command . ' 2>&1', $lines, $code);

    return ['code' => (int) $code, 'output' => implode("\n", $lines)];
}

$request = [
    'id' => $siteId,
    'slug' => 'owasys-bin-opus-smoke-demo',
    'name' => 'OWASYS Bin OPUS Smoke Demo',
    'kind' => 'fullstack',
    'root_path' => $siteRoot,
    'blueprint' => 'opus-site-standard',
    'default_locale' => 'fr',
    'theme' => 'starter',
    'controllers' => ['home', 'articles'],
    'routes' => [
        ['id' => 'home.index', 'path' => '/', 'controller' => 'home'],
        ['id' => 'articles.index', 'path' => '/articles', 'controller' => 'articles'],
    ],
    'datasources' => [],
    'security_profiles' => [['id' => 'admin', 'permissions' => ['*']]],
    'workflows' => [],
];

if (!is_dir(dirname($requestFile)) && !mkdir(dirname($requestFile), 0777, true) && !is_dir(dirname($requestFile))) {
    fwrite(STDERR, "OWASYS_BIN_OPUS_SMOKE_VAR_DIR_CREATE_FAILED\n");
    exit(1);
}

file_put_contents($requestFile, json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
owasys_bin_opus_remove_tree($absoluteRoot);

try {
    $bin = escapeshellarg($root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'opus');
    $requestArg = escapeshellarg($requestFile);

    $dryRun = owasys_bin_opus_exec(PHP_BINARY . ' ' . $bin . ' owasys:create ' . $requestArg . ' --dry-run');
    if ($dryRun['code'] !== 0 || !str_contains($dryRun['output'], 'OWASYS_APPLICATION_CREATE_DRY_RUN_OK: ' . $siteRoot)) {
        fwrite(STDERR, $dryRun['output'] . "\n");
        throw new RuntimeException('OWASYS_BIN_OPUS_DRY_RUN_FAILED');
    }
    if (file_exists($absoluteRoot)) {
        throw new RuntimeException('OWASYS_BIN_OPUS_DRY_RUN_MUTATED_DISK');
    }

    $write = owasys_bin_opus_exec(PHP_BINARY . ' ' . $bin . ' owasys:create ' . $requestArg . ' --write --validate');
    if ($write['code'] !== 0 || !str_contains($write['output'], 'OWASYS_APPLICATION_CREATE_WRITE_OK: ' . $siteRoot)) {
        fwrite(STDERR, $write['output'] . "\n");
        throw new RuntimeException('OWASYS_BIN_OPUS_WRITE_FAILED');
    }
    if (!str_contains($write['output'], 'OWASYS_APPLICATION_CREATE_VALIDATE_OK: ' . $siteId)) {
        fwrite(STDERR, $write['output'] . "\n");
        throw new RuntimeException('OWASYS_BIN_OPUS_VALIDATE_FAILED');
    }

    foreach (['config/site.json', 'config/routes.json', 'config/owasys-creation-manifest.json', 'application/default', 'application/home/views/index.php', 'www/index.php'] as $required) {
        $path = $absoluteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $required);
        if (!file_exists($path)) {
            throw new RuntimeException('OWASYS_BIN_OPUS_REQUIRED_OUTPUT_MISSING: ' . $required);
        }
    }
} finally {
    owasys_bin_opus_remove_tree($absoluteRoot);
    @unlink($requestFile);
}

if (file_exists($absoluteRoot)) {
    fwrite(STDERR, "OWASYS_BIN_OPUS_SMOKE_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_BIN_OPUS_OWASYS_CREATE_SMOKE_OK\n";
