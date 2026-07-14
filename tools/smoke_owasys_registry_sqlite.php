<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\RegistryRepository;

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$seedFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'registry.seed.json';
$databaseRelative = 'var/registry/owasys-registry-sqlite-smoke.sqlite';
$databasePath = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $databaseRelative);
$badSeedFile = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'owasys-registry-sqlite-bad-seed.json';

function owasys_registry_sqlite_cleanup(string $databasePath, string $badSeedFile): void
{
    @unlink($databasePath);
    @unlink($databasePath . '-shm');
    @unlink($databasePath . '-wal');
    @unlink($badSeedFile);

    $registryDir = dirname($databasePath);
    if (is_dir($registryDir) && count(scandir($registryDir) ?: []) === 2) {
        @rmdir($registryDir);
    }
    $varDir = dirname($registryDir);
    if (is_dir($varDir) && count(scandir($varDir) ?: []) === 2) {
        @rmdir($varDir);
    }

    $badSeedDir = dirname($badSeedFile);
    if (is_dir($badSeedDir) && count(scandir($badSeedDir) ?: []) === 2) {
        @rmdir($badSeedDir);
    }
}

if (!class_exists(SQLite3::class)) {
    fwrite(STDERR, "OWASYS_REGISTRY_SQLITE3_EXTENSION_MISSING\n");
    exit(1);
}
if (!is_dir($siteRoot) || !is_file($seedFile)) {
    fwrite(STDERR, "OWASYS_REGISTRY_SQLITE_REQUIRED_SOURCE_MISSING\n");
    exit(1);
}

owasys_registry_sqlite_cleanup($databasePath, $badSeedFile);

try {
    $repository = RegistryRepository::forOwasysSite($siteRoot, $root, $databaseRelative);
    $sync = $repository->synchronize($seedFile);
    if (($sync['contract'] ?? null) !== RegistryRepository::CONTRACT || ($sync['database'] ?? null) !== $databaseRelative) {
        throw new RuntimeException('OWASYS_REGISTRY_SQLITE_SYNC_INVALID');
    }
    if (($sync['seed_imported'] ?? 0) < 2 || ($sync['total'] ?? 0) < 2) {
        throw new RuntimeException('OWASYS_REGISTRY_SQLITE_SYNC_COUNTS_INVALID');
    }
    if (!is_file($databasePath)) {
        throw new RuntimeException('OWASYS_REGISTRY_SQLITE_DATABASE_MISSING');
    }

    $entries = $repository->entries();
    $byId = [];
    foreach ($entries as $entry) {
        if (is_array($entry) && isset($entry['id'])) {
            $byId[(string) $entry['id']] = $entry;
        }
    }

    foreach (['owasys', 'demo-app'] as $id) {
        if (!isset($byId[$id])) {
            throw new RuntimeException('OWASYS_REGISTRY_SQLITE_ENTRY_MISSING: ' . $id);
        }
    }
    if (($byId['demo-app']['root_path'] ?? null) !== 'sites/demo-app' || ($byId['demo-app']['kind'] ?? null) !== 'fullstack') {
        throw new RuntimeException('OWASYS_REGISTRY_SQLITE_DEMO_ENTRY_INVALID');
    }

    $db = new SQLite3($databasePath);
    try {
        foreach (['owasys_applications', 'owasys_application_events', 'owasys_runtime_context'] as $table) {
            $exists = $db->querySingle("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '" . SQLite3::escapeString($table) . "'");
            if ($exists !== $table) {
                throw new RuntimeException('OWASYS_REGISTRY_SQLITE_TABLE_MISSING: ' . $table);
            }
        }
    } finally {
        $db->close();
    }

    $badParent = dirname($badSeedFile);
    if (!is_dir($badParent) && !mkdir($badParent, 0775, true) && !is_dir($badParent)) {
        throw new RuntimeException('OWASYS_REGISTRY_SQLITE_BAD_SEED_DIR_CREATE_FAILED');
    }
    if (file_put_contents($badSeedFile, json_encode(['contract' => 'BAD'], JSON_PRETTY_PRINT) . "\n") === false) {
        throw new RuntimeException('OWASYS_REGISTRY_SQLITE_BAD_SEED_WRITE_FAILED');
    }

    try {
        $repository->synchronize($badSeedFile);
        throw new RuntimeException('OWASYS_REGISTRY_SQLITE_BAD_SEED_NOT_REJECTED');
    } catch (RuntimeException $exception) {
        if (!str_starts_with($exception->getMessage(), 'OWASYS_REGISTRY_SEED_INVALID:')) {
            throw $exception;
        }
    }
} finally {
    owasys_registry_sqlite_cleanup($databasePath, $badSeedFile);
}

if (is_file($databasePath) || is_file($badSeedFile)) {
    fwrite(STDERR, "OWASYS_REGISTRY_SQLITE_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_REGISTRY_SQLITE_SMOKE_OK\n";
