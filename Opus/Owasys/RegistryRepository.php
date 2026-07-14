<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

/**
 * Runtime SQLite registry for OWASYS-managed OPUS applications.
 *
 * The JSON seed remains a controlled bootstrap source only; the registry view
 * reads normalized application rows from SQLite after schema/bootstrap sync.
 */
final class RegistryRepository
{
    public const CONTRACT = 'OWASYS_REGISTRY_SQLITE_V1';

    private const DEFAULT_DATABASE = 'var/registry/owasys.sqlite';

    /** @var list<string> */
    private const ALLOWED_KINDS = ['fullstack', 'frontend', 'backend', 'package'];

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot,
        private readonly string $databaseRelative = self::DEFAULT_DATABASE,
    ) {
        if (!class_exists(SQLite3::class)) {
            throw new RuntimeException('OWASYS_REGISTRY_SQLITE3_EXTENSION_MISSING');
        }
        $this->assertSafeRelativePath($this->databaseRelative, 'OWASYS_REGISTRY_DATABASE_PATH_INVALID');
    }

    public static function forOwasysSite(string $siteRoot, ?string $opusRoot = null, ?string $databaseRelative = null): self
    {
        return new self(
            rtrim($siteRoot, DIRECTORY_SEPARATOR),
            rtrim($opusRoot ?? dirname(rtrim($siteRoot, DIRECTORY_SEPARATOR), 2), DIRECTORY_SEPARATOR),
            $databaseRelative ?? self::DEFAULT_DATABASE,
        );
    }

    /** @return array{contract:string,database:string,seed_imported:int,discovered_imported:int,total:int} */
    public function synchronize(string $seedFile): array
    {
        $db = $this->open();
        try {
            $this->ensureSchema($db);
            $seedImported = $this->importSeed($db, $seedFile);
            $discoveredImported = $this->importDiscoveredSites($db);
            $total = $this->countApplications($db);
        } finally {
            $db->close();
        }

        return [
            'contract' => self::CONTRACT,
            'database' => $this->relativeDatabasePath(),
            'seed_imported' => $seedImported,
            'discovered_imported' => $discoveredImported,
            'total' => $total,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function entries(): array
    {
        $db = $this->open();
        try {
            $this->ensureSchema($db);
            $result = $db->query('SELECT id, slug, name, kind, root_path, public_root, default_locale, theme, status, blueprint, generated_by, role, source, updated_at FROM owasys_applications ORDER BY id ASC');
            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException('OWASYS_REGISTRY_QUERY_FAILED');
            }

            $entries = [];
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $entries[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'slug' => (string) ($row['slug'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'kind' => (string) ($row['kind'] ?? 'fullstack'),
                    'root_path' => (string) ($row['root_path'] ?? ''),
                    'public_root' => (string) ($row['public_root'] ?? 'www'),
                    'default_locale' => (string) ($row['default_locale'] ?? 'fr'),
                    'theme' => (string) ($row['theme'] ?? 'default'),
                    'status' => (string) ($row['status'] ?? 'discovered'),
                    'blueprint' => (string) ($row['blueprint'] ?? 'unknown'),
                    'generated_by' => (string) ($row['generated_by'] ?? 'unknown'),
                    'role' => (string) ($row['role'] ?? 'standard-opus-application'),
                    'source' => (string) ($row['source'] ?? 'sqlite'),
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                ];
            }
            $result->finalize();
        } finally {
            $db->close();
        }

        return $entries;
    }

    public function databasePath(): string
    {
        return rtrim($this->siteRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($this->databaseRelative, '/'));
    }

    public function relativeDatabasePath(): string
    {
        return trim(str_replace('\\', '/', $this->databaseRelative), '/');
    }

    private function open(): SQLite3
    {
        $path = $this->databasePath();
        $parent = dirname($path);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new RuntimeException('OWASYS_REGISTRY_DATABASE_DIRECTORY_CREATE_FAILED: ' . $this->relativeDatabasePath());
        }

        $db = new SQLite3($path);
        $db->enableExceptions(true);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA foreign_keys = ON');
        return $db;
    }

    private function ensureSchema(SQLite3 $db): void
    {
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS owasys_applications (
    id TEXT PRIMARY KEY,
    slug TEXT NOT NULL,
    name TEXT NOT NULL,
    kind TEXT NOT NULL,
    root_path TEXT NOT NULL,
    public_root TEXT NOT NULL,
    default_locale TEXT NOT NULL,
    theme TEXT NOT NULL,
    status TEXT NOT NULL,
    blueprint TEXT NOT NULL,
    generated_by TEXT NOT NULL,
    role TEXT NOT NULL,
    source TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)
SQL);
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS owasys_application_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id TEXT NOT NULL,
    event_type TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY(application_id) REFERENCES owasys_applications(id) ON DELETE CASCADE
)
SQL);
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS owasys_runtime_context (
    key TEXT PRIMARY KEY,
    value_json TEXT NOT NULL,
    updated_at TEXT NOT NULL
)
SQL);
    }

    private function importSeed(SQLite3 $db, string $seedFile): int
    {
        if (!is_file($seedFile)) {
            throw new RuntimeException('OWASYS_REGISTRY_SEED_MISSING: ' . $this->relativeFromOpusRoot($seedFile));
        }

        $seed = json_decode((string) file_get_contents($seedFile), true);
        if (!is_array($seed) || ($seed['contract'] ?? null) !== 'OWASYS_REGISTRY_SEED_V1') {
            throw new RuntimeException('OWASYS_REGISTRY_SEED_INVALID: ' . $this->relativeFromOpusRoot($seedFile));
        }

        $imported = 0;
        foreach ((array) ($seed['applications'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $this->upsertApplication($db, $entry, 'seed');
            $imported++;
        }

        return $imported;
    }

    private function importDiscoveredSites(SQLite3 $db): int
    {
        $imported = 0;
        foreach (glob(rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json') ?: [] as $siteJsonFile) {
            if (!is_string($siteJsonFile) || !is_file($siteJsonFile)) {
                continue;
            }

            $site = json_decode((string) file_get_contents($siteJsonFile), true);
            if (!is_array($site) || ($site['contract'] ?? null) !== 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL') {
                continue;
            }

            $siteDir = basename(dirname(dirname($siteJsonFile)));
            $siteId = (string) ($site['site_id'] ?? $siteDir);
            $manifestFile = dirname($siteJsonFile) . DIRECTORY_SEPARATOR . 'owasys-creation-manifest.json';
            $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : [];
            $manifest = is_array($manifest) ? $manifest : [];
            $validation = is_array($manifest['validation'] ?? null) ? $manifest['validation'] : [];

            $this->upsertApplication($db, [
                'id' => $siteId,
                'slug' => $siteId,
                'name' => (string) ($site['site_name'] ?? $siteId),
                'kind' => $site['kind'] ?? 'fullstack',
                'root_path' => 'sites/' . $siteDir,
                'public_root' => $site['public_root'] ?? 'www',
                'default_locale' => $site['default_locale'] ?? 'fr',
                'theme' => $site['theme'] ?? 'default',
                'status' => (string) ($validation['status'] ?? (($site['generated_by'] ?? null) === 'owasys' ? 'generated' : 'discovered')),
                'blueprint' => $manifest['blueprint'] ?? $site['blueprint'] ?? 'unknown',
                'generated_by' => $site['generated_by'] ?? ($manifest['generator'] ?? 'unknown'),
                'role' => $site['role'] ?? 'standard-opus-application',
            ], 'discovered');
            $imported++;
        }

        return $imported;
    }

    /** @param array<string,mixed> $entry */
    private function upsertApplication(SQLite3 $db, array $entry, string $source): void
    {
        $normalized = $this->normalizeEntry($entry, $source);
        if ($normalized === null) {
            return;
        }

        $sql = <<<'SQL'
INSERT INTO owasys_applications (
    id, slug, name, kind, root_path, public_root, default_locale, theme, status, blueprint, generated_by, role, source, payload_json, created_at, updated_at
) VALUES (
    :id, :slug, :name, :kind, :root_path, :public_root, :default_locale, :theme, :status, :blueprint, :generated_by, :role, :source, :payload_json, :created_at, :updated_at
)
ON CONFLICT(id) DO UPDATE SET
    slug = excluded.slug,
    name = excluded.name,
    kind = excluded.kind,
    root_path = excluded.root_path,
    public_root = excluded.public_root,
    default_locale = excluded.default_locale,
    theme = excluded.theme,
    status = excluded.status,
    blueprint = excluded.blueprint,
    generated_by = excluded.generated_by,
    role = excluded.role,
    source = excluded.source,
    payload_json = excluded.payload_json,
    updated_at = excluded.updated_at
SQL;

        $stmt = $db->prepare($sql);
        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException('OWASYS_REGISTRY_UPSERT_PREPARE_FAILED: ' . $normalized['id']);
        }

        foreach ($normalized as $key => $value) {
            $stmt->bindValue(':' . $key, (string) $value, SQLITE3_TEXT);
        }
        $payload = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stmt->bindValue(':payload_json', is_string($payload) ? $payload : '{}', SQLITE3_TEXT);
        $now = gmdate('c');
        $stmt->bindValue(':created_at', $now, SQLITE3_TEXT);
        $stmt->bindValue(':updated_at', $now, SQLITE3_TEXT);

        $result = $stmt->execute();
        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }
        $stmt->close();
    }

    /** @param array<string,mixed> $entry @return array<string,string>|null */
    private function normalizeEntry(array $entry, string $source): ?array
    {
        $id = trim((string) ($entry['id'] ?? $entry['site_id'] ?? ''));
        if ($id === '') {
            return null;
        }
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $id) !== 1) {
            throw new RuntimeException('OWASYS_REGISTRY_APPLICATION_ID_INVALID: ' . $id);
        }

        return [
            'id' => $id,
            'slug' => (string) ($entry['slug'] ?? $id),
            'name' => (string) ($entry['name'] ?? $entry['site_name'] ?? $id),
            'kind' => $this->normalizeKind($entry['kind'] ?? 'fullstack'),
            'root_path' => (string) ($entry['root_path'] ?? ('sites/' . $id)),
            'public_root' => (string) ($entry['public_root'] ?? 'www'),
            'default_locale' => (string) ($entry['default_locale'] ?? 'fr'),
            'theme' => (string) ($entry['theme'] ?? 'default'),
            'status' => (string) ($entry['status'] ?? 'discovered'),
            'blueprint' => (string) ($entry['blueprint'] ?? 'unknown'),
            'generated_by' => (string) ($entry['generated_by'] ?? 'unknown'),
            'role' => (string) ($entry['role'] ?? 'standard-opus-application'),
            'source' => $source,
        ];
    }

    private function normalizeKind(mixed $kind): string
    {
        $value = strtolower(trim((string) $kind));
        return in_array($value, self::ALLOWED_KINDS, true) ? $value : 'fullstack';
    }

    private function countApplications(SQLite3 $db): int
    {
        $count = $db->querySingle('SELECT COUNT(*) FROM owasys_applications');
        return is_numeric($count) ? (int) $count : 0;
    }

    private function assertSafeRelativePath(string $path, string $error): void
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');
        if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1 || str_contains($normalized, '..')) {
            throw new RuntimeException($error . ': ' . $path);
        }
    }

    private function relativeFromOpusRoot(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', realpath($this->opusRoot) ?: $this->opusRoot), '/') . '/';
        $normalized = str_replace('\\', '/', realpath($path) ?: $path);
        return str_starts_with($normalized, $root) ? substr($normalized, strlen($root)) : $normalized;
    }
}
