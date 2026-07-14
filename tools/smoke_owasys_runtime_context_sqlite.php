<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\RegistryRepository;

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$seedFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'registry.seed.json';
$databaseRelative = 'var/registry/owasys-runtime-context-smoke.sqlite';
$databasePath = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $databaseRelative);

function owasys_runtime_context_sqlite_cleanup(string $databasePath): void
{
    @unlink($databasePath);
    @unlink($databasePath . '-shm');
    @unlink($databasePath . '-wal');
    $registryDir = dirname($databasePath);
    if (is_dir($registryDir) && count(scandir($registryDir) ?: []) === 2) {
        @rmdir($registryDir);
    }
    $varDir = dirname($registryDir);
    if (is_dir($varDir) && count(scandir($varDir) ?: []) === 2) {
        @rmdir($varDir);
    }
}

if (!class_exists(SQLite3::class)) {
    fwrite(STDERR, "OWASYS_RUNTIME_CONTEXT_SQLITE3_EXTENSION_MISSING\n");
    exit(1);
}

owasys_runtime_context_sqlite_cleanup($databasePath);

try {
    $repository = RegistryRepository::forOwasysSite($siteRoot, $root, $databaseRelative);
    $sync = $repository->synchronize($seedFile);
    if (($sync['contract'] ?? null) !== RegistryRepository::CONTRACT) {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_SYNC_INVALID');
    }

    $demo = null;
    foreach ($repository->entries() as $entry) {
        if (is_array($entry) && ($entry['id'] ?? null) === 'demo-app') {
            $demo = $entry;
            break;
        }
    }
    if (!is_array($demo)) {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_DEMO_ENTRY_MISSING');
    }

    $repository->setCurrentApplication($demo, 'runtime-context-smoke');
    $current = $repository->currentApplication();
    if (!is_array($current) || ($current['id'] ?? null) !== 'demo-app' || ($current['context_contract'] ?? null) !== 'OWASYS_RUNTIME_CURRENT_APP_V1') {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_CURRENT_APP_INVALID');
    }
    if ($repository->eventCount('select_app') !== 1) {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_SELECT_EVENT_MISSING');
    }

    $repository->startCreationFlow('runtime-context-smoke');
    $creation = $repository->runtimeValue('creation_flow');
    if (!is_array($creation) || ($creation['contract'] ?? null) !== 'OWASYS_RUNTIME_CREATION_FLOW_V1') {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_CREATION_FLOW_INVALID');
    }
    if ($repository->eventCount('create_new_app') !== 1) {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_CREATE_EVENT_MISSING');
    }

    $repository->clearCurrentApplication('runtime-context-smoke');
    if ($repository->currentApplication() !== null) {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_CLEAR_FAILED');
    }
    if ($repository->eventCount('clear_app_context') !== 1) {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_CLEAR_EVENT_MISSING');
    }

    $repository->setCurrentApplication($demo, 'runtime-context-smoke');
    $repository->logout('runtime-context-smoke');
    if ($repository->currentApplication() !== null || $repository->runtimeValue('creation_flow') !== null) {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_LOGOUT_CLEAR_FAILED');
    }
    if ($repository->eventCount('logout') !== 1 || $repository->eventCount() < 5) {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_LOGOUT_EVENT_MISSING');
    }

    $recent = $repository->recentEvents(3);
    if (count($recent) !== 3 || ($recent[0]['event_type'] ?? null) !== 'logout') {
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_RECENT_EVENTS_INVALID');
    }
    try {
        $repository->recentEvents(0);
        throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_BAD_RECENT_LIMIT_NOT_REJECTED');
    } catch (RuntimeException $exception) {
        if (!str_starts_with($exception->getMessage(), 'OWASYS_RUNTIME_EVENT_LIMIT_INVALID:')) {
            throw $exception;
        }
    }
} finally {
    owasys_runtime_context_sqlite_cleanup($databasePath);
}

if (is_file($databasePath)) {
    fwrite(STDERR, "OWASYS_RUNTIME_CONTEXT_SQLITE_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_RUNTIME_CONTEXT_SQLITE_SMOKE_OK\n";
