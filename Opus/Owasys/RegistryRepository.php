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
 * The JSON seed remains a controlled bootstrap source only; runtime context
 * and application events are persisted in SQLite next to the registry rows.
 */
final class RegistryRepository
{
    public const CONTRACT = 'OWASYS_REGISTRY_SQLITE_V1';

    private const DEFAULT_DATABASE = 'var/registry/owasys.sqlite';
    private const SYSTEM_APPLICATION_ID = 'owasys';

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
                $entries[] = $this->entryFromRow($row);
            }
            $result->finalize();
        } finally {
            $db->close();
        }

        return $entries;
    }

    /** @return array<string,mixed>|null */
    public function currentApplication(): ?array
    {
        $value = $this->runtimeValue('current_app');
        return is_array($value) ? $value : null;
    }

    /** @param array<string,mixed> $entry */
    public function setCurrentApplication(array $entry, ?string $actorId = null): void
    {
        $db = $this->open();
        try {
            $this->ensureSchema($db);
            $normalized = $this->normalizeEntry($entry, 'runtime');
            if ($normalized === null) {
                throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_CURRENT_APP_INVALID');
            }
            if (!$this->applicationExists($db, $normalized['id'])) {
                $this->upsertApplication($db, $entry, 'runtime');
            }

            $value = array_merge($entry, [
                'id' => $normalized['id'],
                'selected_at' => gmdate('c'),
                'selected_by' => (string) ($actorId ?? 'runtime'),
                'context_contract' => 'OWASYS_RUNTIME_CURRENT_APP_V1',
            ]);
            $this->setContextValue($db, 'current_app', $value);
            $this->recordEventOnDb($db, $normalized['id'], 'select_app', [
                'actor_id' => $actorId,
                'application' => $value,
            ]);
        } finally {
            $db->close();
        }
    }

    public function clearCurrentApplication(?string $actorId = null): void
    {
        $db = $this->open();
        try {
            $this->ensureSchema($db);
            $current = $this->getContextValue($db, 'current_app');
            $this->deleteContextValue($db, 'current_app');
            $applicationId = is_array($current) && isset($current['id']) ? (string) $current['id'] : self::SYSTEM_APPLICATION_ID;
            $this->recordEventOnDb($db, $this->safeEventApplicationId($db, $applicationId), 'clear_app_context', [
                'actor_id' => $actorId,
                'previous_application' => $current,
            ]);
        } finally {
            $db->close();
        }
    }

    public function startCreationFlow(?string $actorId = null): void
    {
        $db = $this->open();
        try {
            $this->ensureSchema($db);
            $value = [
                'contract' => 'OWASYS_RUNTIME_CREATION_FLOW_V1',
                'status' => 'started',
                'started_at' => gmdate('c'),
                'started_by' => (string) ($actorId ?? 'runtime'),
            ];
            $this->setContextValue($db, 'creation_flow', $value);
            $this->recordEventOnDb($db, $this->safeEventApplicationId($db, self::SYSTEM_APPLICATION_ID), 'create_new_app', $value);
        } finally {
            $db->close();
        }
    }

    /**
     * Persist a controlled validation action for the currently selected application.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $inspection
     * @return array<string,mixed>
     */
    public function recordStructureValidation(array $entry, array $inspection, ?string $actorId = null): array
    {
        $applicationId = (string) ($inspection['site_id'] ?? ($entry['id'] ?? ''));
        if ($applicationId === '') {
            throw new RuntimeException('OWASYS_STRUCTURE_VALIDATION_APPLICATION_ID_MISSING');
        }
        if (($inspection['inspection_contract'] ?? null) !== ApplicationInspector::CONTRACT) {
            throw new RuntimeException('OWASYS_STRUCTURE_VALIDATION_INSPECTION_CONTRACT_INVALID');
        }

        $db = $this->open();
        try {
            $this->ensureSchema($db);
            $registryEntry = [
                'id' => $applicationId,
                'slug' => $applicationId,
                'name' => (string) ($inspection['site_name'] ?? ($entry['name'] ?? $applicationId)),
                'kind' => (string) ($inspection['kind'] ?? ($entry['kind'] ?? 'fullstack')),
                'root_path' => (string) ($inspection['root_path'] ?? ($entry['root_path'] ?? ('sites/' . $applicationId))),
                'public_root' => (string) ($inspection['public_root'] ?? ($entry['public_root'] ?? 'www')),
                'default_locale' => (string) ($inspection['default_locale'] ?? ($entry['default_locale'] ?? 'fr')),
                'theme' => (string) ($inspection['theme'] ?? ($entry['theme'] ?? 'default')),
                'status' => 'validated',
                'blueprint' => (string) ($inspection['blueprint'] ?? ($entry['blueprint'] ?? 'unknown')),
                'generated_by' => (string) ($inspection['generated_by'] ?? ($entry['generated_by'] ?? 'unknown')),
                'role' => (string) ($inspection['role'] ?? ($entry['role'] ?? 'standard-opus-application')),
            ];
            $this->upsertApplication($db, $registryEntry, 'inspection');

            $result = [
                'contract' => 'OWASYS_STRUCTURE_VALIDATION_RESULT_V1',
                'status' => 'valid',
                'application_id' => $applicationId,
                'validated_at' => gmdate('c'),
                'validated_by' => (string) ($actorId ?? 'runtime'),
                'state_count' => (int) ($inspection['state_count'] ?? 0),
                'route_count' => (int) ($inspection['route_count'] ?? 0),
                'transition_count' => (int) ($inspection['transition_count'] ?? 0),
                'inspection_contract' => (string) ($inspection['inspection_contract'] ?? ''),
                'fsm_contract' => (string) ($inspection['fsm_contract'] ?? ''),
            ];
            $this->setContextValue($db, 'last_structure_validation', $result);
            $this->recordEventOnDb($db, $applicationId, 'validate_application', [
                'actor_id' => $actorId,
                'result' => $result,
                'inspection' => $inspection,
            ]);
        } finally {
            $db->close();
        }

        return $result;
    }

    public function logout(?string $actorId = null): void
    {
        $db = $this->open();
        try {
            $this->ensureSchema($db);
            $current = $this->getContextValue($db, 'current_app');
            $this->deleteContextValue($db, 'current_app');
            $this->deleteContextValue($db, 'creation_flow');
            $applicationId = is_array($current) && isset($current['id']) ? (string) $current['id'] : self::SYSTEM_APPLICATION_ID;
            $this->recordEventOnDb($db, $this->safeEventApplicationId($db, $applicationId), 'logout', [
                'actor_id' => $actorId,
                'previous_application' => $current,
            ]);
        } finally {
            $db->close();
        }
    }

    /** @return array<string,mixed>|null */
    public function runtimeValue(string $key): ?array
    {
        $this->assertRuntimeKey($key);
        $db = $this->open();
        try {
            $this->ensureSchema($db);
            return $this->getContextValue($db, $key);
        } finally {
            $db->close();
        }
    }

    public function eventCount(?string $eventType = null): int
    {
        $db = $this->open();
        try {
            $this->ensureSchema($db);
            if ($eventType === null) {
                $count = $db->querySingle('SELECT COUNT(*) FROM owasys_application_events');
                return is_numeric($count) ? (int) $count : 0;
            }
            $stmt = $db->prepare('SELECT COUNT(*) FROM owasys_application_events WHERE event_type = :event_type');
            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException('OWASYS_RUNTIME_EVENT_COUNT_PREPARE_FAILED');
            }
            $stmt->bindValue(':event_type', $eventType, SQLITE3_TEXT);
            $result = $stmt->execute();
            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException('OWASYS_RUNTIME_EVENT_COUNT_QUERY_FAILED');
            }
            $row = $result->fetchArray(SQLITE3_NUM);
            $result->finalize();
            $stmt->close();
            return is_array($row) && is_numeric($row[0] ?? null) ? (int) $row[0] : 0;
        } finally {
            $db->close();
        }
    }

    /** @return list<array{id:int,application_id:string,event_type:string,payload:array<string,mixed>,created_at:string}> */
    public function recentEvents(int $limit = 8): array
    {
        if ($limit < 1 || $limit > 50) {
            throw new RuntimeException('OWASYS_RUNTIME_EVENT_LIMIT_INVALID: ' . $limit);
        }

        $db = $this->open();
        try {
            $this->ensureSchema($db);
            $stmt = $db->prepare('SELECT id, application_id, event_type, payload_json, created_at FROM owasys_application_events ORDER BY id DESC LIMIT :limit');
            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException('OWASYS_RUNTIME_EVENT_RECENT_PREPARE_FAILED');
            }
            $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException('OWASYS_RUNTIME_EVENT_RECENT_QUERY_FAILED');
            }
            $events = [];
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
                $events[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'application_id' => (string) ($row['application_id'] ?? ''),
                    'event_type' => (string) ($row['event_type'] ?? ''),
                    'payload' => is_array($payload) ? $payload : [],
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
            $result->finalize();
            $stmt->close();
        } finally {
            $db->close();
        }

        return $events;
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
            if (is_array($entry)) {
                $this->upsertApplication($db, $entry, 'seed');
                $imported++;
            }
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
        $now = gmdate('c');
        $stmt->bindValue(':payload_json', is_string($payload) ? $payload : '{}', SQLITE3_TEXT);
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

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function entryFromRow(array $row): array
    {
        return [
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

    private function applicationExists(SQLite3 $db, string $applicationId): bool
    {
        $stmt = $db->prepare('SELECT id FROM owasys_applications WHERE id = :id LIMIT 1');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException('OWASYS_REGISTRY_APPLICATION_EXISTS_PREPARE_FAILED');
        }
        $stmt->bindValue(':id', $applicationId, SQLITE3_TEXT);
        $result = $stmt->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException('OWASYS_REGISTRY_APPLICATION_EXISTS_QUERY_FAILED');
        }
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $result->finalize();
        $stmt->close();
        return is_array($row);
    }

    /** @return array<string,mixed>|null */
    private function getContextValue(SQLite3 $db, string $key): ?array
    {
        $this->assertRuntimeKey($key);
        $stmt = $db->prepare('SELECT value_json FROM owasys_runtime_context WHERE key = :key LIMIT 1');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_READ_PREPARE_FAILED: ' . $key);
        }
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $result = $stmt->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_READ_QUERY_FAILED: ' . $key);
        }
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $result->finalize();
        $stmt->close();
        if (!is_array($row)) {
            return null;
        }
        $decoded = json_decode((string) ($row['value_json'] ?? ''), true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $value */
    private function setContextValue(SQLite3 $db, string $key, array $value): void
    {
        $this->assertRuntimeKey($key);
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO owasys_runtime_context (key, value_json, updated_at) VALUES (:key, :value_json, :updated_at) ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = excluded.updated_at');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_WRITE_PREPARE_FAILED: ' . $key);
        }
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value_json', is_string($json) ? $json : '{}', SQLITE3_TEXT);
        $stmt->bindValue(':updated_at', gmdate('c'), SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }
        $stmt->close();
    }

    private function deleteContextValue(SQLite3 $db, string $key): void
    {
        $this->assertRuntimeKey($key);
        $stmt = $db->prepare('DELETE FROM owasys_runtime_context WHERE key = :key');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_DELETE_PREPARE_FAILED: ' . $key);
        }
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }
        $stmt->close();
    }

    /** @param array<string,mixed> $payload */
    private function recordEventOnDb(SQLite3 $db, string $applicationId, string $eventType, array $payload): void
    {
        if (preg_match('/^[a-z][a-z0-9_:-]*$/', $eventType) !== 1) {
            throw new RuntimeException('OWASYS_RUNTIME_EVENT_TYPE_INVALID: ' . $eventType);
        }
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO owasys_application_events (application_id, event_type, payload_json, created_at) VALUES (:application_id, :event_type, :payload_json, :created_at)');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException('OWASYS_RUNTIME_EVENT_INSERT_PREPARE_FAILED: ' . $eventType);
        }
        $stmt->bindValue(':application_id', $applicationId, SQLITE3_TEXT);
        $stmt->bindValue(':event_type', $eventType, SQLITE3_TEXT);
        $stmt->bindValue(':payload_json', is_string($json) ? $json : '{}', SQLITE3_TEXT);
        $stmt->bindValue(':created_at', gmdate('c'), SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }
        $stmt->close();
    }

    private function safeEventApplicationId(SQLite3 $db, string $applicationId): string
    {
        if ($applicationId !== '' && $this->applicationExists($db, $applicationId)) {
            return $applicationId;
        }
        if ($this->applicationExists($db, self::SYSTEM_APPLICATION_ID)) {
            return self::SYSTEM_APPLICATION_ID;
        }
        throw new RuntimeException('OWASYS_RUNTIME_EVENT_SYSTEM_APPLICATION_MISSING');
    }

    private function assertRuntimeKey(string $key): void
    {
        if (preg_match('/^[a-z][a-z0-9_:-]*$/', $key) !== 1) {
            throw new RuntimeException('OWASYS_RUNTIME_CONTEXT_KEY_INVALID: ' . $key);
        }
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
