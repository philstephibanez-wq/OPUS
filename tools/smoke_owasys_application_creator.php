<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationCreator;

$root = dirname(__DIR__);
$siteId = 'owasys-creator-smoke-demo';
$siteRoot = 'sites/' . $siteId;
$absoluteRoot = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $siteRoot);

/**
 * Removes a directory tree created by this smoke test.
 */
function owasys_creator_remove_tree(string $path): void
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

$request = [
    'id' => $siteId,
    'slug' => 'owasys-creator-smoke-demo',
    'name' => 'OWASYS Creator Smoke Demo',
    'kind' => 'fullstack',
    'root_path' => $siteRoot,
    'blueprint' => 'opus-site-standard',
    'default_locale' => 'fr',
    'theme' => 'starter',
    'controllers' => ['home', 'articles'],
    'routes' => [
        ['id' => 'home.index', 'path' => '/', 'state' => 'home', 'controller' => 'home'],
        ['id' => 'articles.index', 'path' => '/articles', 'state' => 'articles', 'controller' => 'articles'],
    ],
    'datasources' => [],
    'security_profiles' => [['id' => 'admin', 'permissions' => ['*']]],
    'workflows' => [],
];

owasys_creator_remove_tree($absoluteRoot);

try {
    $creator = new ApplicationCreator($root);

    $dryRun = $creator->create($request, false, true);
    if (($dryRun['mode'] ?? null) !== 'dry-run') {
        throw new RuntimeException('OWASYS_CREATOR_DRY_RUN_RESULT_INVALID');
    }
    if (($dryRun['application_fsm'] ?? null) !== $siteRoot . '/config/application.fsm.json') {
        throw new RuntimeException('OWASYS_CREATOR_DRY_RUN_FSM_RESULT_INVALID');
    }
    if (file_exists($absoluteRoot)) {
        throw new RuntimeException('OWASYS_CREATOR_DRY_RUN_MUTATED_DISK');
    }

    $write = $creator->create($request, true, true);
    if (($write['mode'] ?? null) !== 'write') {
        throw new RuntimeException('OWASYS_CREATOR_WRITE_RESULT_INVALID');
    }
    if (($write['validation']['status'] ?? null) !== 'ok') {
        throw new RuntimeException('OWASYS_CREATOR_VALIDATION_RESULT_INVALID');
    }
    if (($write['validation']['application_fsm'] ?? null) !== $siteRoot . '/config/application.fsm.json') {
        throw new RuntimeException('OWASYS_CREATOR_VALIDATION_FSM_RESULT_INVALID');
    }

    foreach (['config/site.json', 'config/routes.json', 'config/application.fsm.json', 'config/fsm.json', 'config/owasys-creation-manifest.json', 'application/default', 'application/states/home/views/index.php', 'www/index.php'] as $required) {
        $path = $absoluteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $required);
        if (!file_exists($path)) {
            throw new RuntimeException('OWASYS_CREATOR_REQUIRED_OUTPUT_MISSING: ' . $required);
        }
    }
    if (file_exists($absoluteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'home')) {
        throw new RuntimeException('OWASYS_CREATOR_LEGACY_STATE_ROOT_PRESENT');
    }

    $manifest = json_decode((string) file_get_contents($absoluteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'owasys-creation-manifest.json'), true);
    if (!is_array($manifest) || ($manifest['application_fsm'] ?? null) !== $siteRoot . '/config/application.fsm.json') {
        throw new RuntimeException('OWASYS_CREATOR_MANIFEST_FSM_MISSING');
    }

    $command = PHP_BINARY . ' ' . escapeshellarg($root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'opus') . ' validate:site ' . escapeshellarg($siteId);
    passthru($command, $exitCode);
    if ((int) $exitCode !== 0) {
        throw new RuntimeException('OWASYS_CREATOR_VALIDATE_SITE_COMMAND_FAILED');
    }
} finally {
    owasys_creator_remove_tree($absoluteRoot);
}

if (file_exists($absoluteRoot)) {
    fwrite(STDERR, "OWASYS_CREATOR_SMOKE_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_APPLICATION_CREATOR_SMOKE_OK\n";
