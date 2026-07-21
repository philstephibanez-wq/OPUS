<?php
declare(strict_types=1);

final class OwasysRegistryRepository
{
    public const CONTRACT = 'OWASYS_REGISTRY_SQLITE_V1';

    private const DEFAULT_DATABASE = 'var/registry/owasys.sqlite';
    private const SYSTEM_APPLICATION_ID = 'owasys';

    /** @var list<string> */
    private const ALLOWED_KINDS = [
        'fullstack',
        'frontend',
        'backend',
        'package',
    ];

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot,
        private readonly string $databaseRelative = self::DEFAULT_DATABASE
    ) {
        if (!class_exists(SQLite3::class)) {
            throw new RuntimeException(
                'OWASYS_REGISTRY_SQLITE3_EXTENSION_MISSING'
            );
        }

        $this->assertSafeRelativePath(
            $this->databaseRelative,
            'OWASYS_REGISTRY_DATABASE_PATH_INVALID'
        );
    }

    public static function forSite(
        string $siteRoot,
        string $opusRoot,
        ?string $databaseRelative = null
    ): self {
        return new self(
            rtrim($siteRoot, DIRECTORY_SEPARATOR),
            rtrim($opusRoot, DIRECTORY_SEPARATOR),
            $databaseRelative ?? self::DEFAULT_DATABASE
        );
    }

    /**
     * @return array{
     *   contract:string,
     *   database:string,
     *   seed_imported:int,
     *   discovered_imported:int,
     *   total:int
     * }
     */
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
            $result = $db->query(
                'SELECT id, slug, name, kind, root_path, public_root, '
                . 'default_locale, theme, status, blueprint, generated_by, '
                . 'role, source, updated_at '
                . 'FROM owasys_applications ORDER BY id ASC'
            );

            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException('OWASYS_REGISTRY_QUERY_FAILED');
            }

            $entries = [];

            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $entries[] = $this->entryFromRow($row);
            }

            $result->finalize();

            return $entries;
        } finally {
            $db->close();
        }
    }

    /** @return list<array<string,mixed>> */
    public function recentEvents(int $limit = 8): array
    {
        if ($limit < 1 || $limit > 50) {
            throw new RuntimeException(
                'OWASYS_RUNTIME_EVENT_LIMIT_INVALID:' . $limit
            );
        }

        $db = $this->open();

        try {
            $this->ensureSchema($db);
            $stmt = $db->prepare(
                'SELECT id, application_id, event_type, payload_json, '
                . 'created_at FROM owasys_application_events '
                . 'ORDER BY id DESC LIMIT :limit'
            );

            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException(
                    'OWASYS_RUNTIME_EVENT_RECENT_PREPARE_FAILED'
                );
            }

            $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
            $result = $stmt->execute();

            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException(
                    'OWASYS_RUNTIME_EVENT_RECENT_QUERY_FAILED'
                );
            }

            $events = [];

            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $payload = json_decode(
                    (string) ($row['payload_json'] ?? '{}'),
                    true
                );

                $events[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'application_id' => (string) (
                        $row['application_id'] ?? ''
                    ),
                    'event_type' => (string) ($row['event_type'] ?? ''),
                    'payload' => is_array($payload) ? $payload : [],
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }

            $result->finalize();
            $stmt->close();

            return $events;
        } finally {
            $db->close();
        }
    }

    /** @param array<string,mixed> $entry */
    public function setCurrentApplication(
        array $entry,
        string $actorId
    ): void {
        $db = $this->open();

        try {
            $this->ensureSchema($db);
            $normalized = $this->normalizeEntry($entry, 'runtime');

            if (!$this->applicationExists($db, $normalized['id'])) {
                $this->upsertApplication($db, $entry, 'runtime');
            }

            $value = array_replace($entry, [
                'id' => $normalized['id'],
                'selected_at' => gmdate('c'),
                'selected_by' => $actorId,
                'context_contract' => 'OWASYS_RUNTIME_CURRENT_APP_V1',
            ]);

            $this->setContextValue($db, 'current_app', $value);
            $this->recordEvent(
                $db,
                $normalized['id'],
                'select_app',
                [
                    'actor_id' => $actorId,
                    'application' => $value,
                ]
            );
        } finally {
            $db->close();
        }
    }

    public function clearCurrentApplication(string $actorId): void
    {
        $db = $this->open();

        try {
            $this->ensureSchema($db);
            $current = $this->getContextValue($db, 'current_app');
            $this->deleteContextValue($db, 'current_app');

            $applicationId = is_array($current)
                ? (string) ($current['id'] ?? '')
                : '';

            $this->recordEvent(
                $db,
                $this->safeEventApplicationId($db, $applicationId),
                'clear_app_context',
                [
                    'actor_id' => $actorId,
                    'previous_application' => $current,
                ]
            );
        } finally {
            $db->close();
        }
    }

    public function startCreationFlow(string $actorId): void
    {
        $db = $this->open();

        try {
            $this->ensureSchema($db);
            $value = [
                'contract' => 'OWASYS_RUNTIME_CREATION_FLOW_V1',
                'status' => 'started',
                'started_at' => gmdate('c'),
                'started_by' => $actorId,
            ];

            $this->setContextValue($db, 'creation_flow', $value);
            $this->recordEvent(
                $db,
                $this->safeEventApplicationId(
                    $db,
                    self::SYSTEM_APPLICATION_ID
                ),
                'create_new_app',
                $value
            );
        } finally {
            $db->close();
        }
    }

    public function relativeDatabasePath(): string
    {
        return trim(
            str_replace('\\', '/', $this->databaseRelative),
            '/'
        );
    }

    private function databasePath(): string
    {
        return rtrim($this->siteRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $this->relativeDatabasePath()
            );
    }

    private function open(): SQLite3
    {
        $path = $this->databasePath();
        $parent = dirname($path);

        if (
            !is_dir($parent)
            && !mkdir($parent, 0775, true)
            && !is_dir($parent)
        ) {
            throw new RuntimeException(
                'OWASYS_REGISTRY_DATABASE_DIRECTORY_CREATE_FAILED:'
                . $this->relativeDatabasePath()
            );
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
    FOREIGN KEY(application_id)
        REFERENCES owasys_applications(id)
        ON DELETE CASCADE
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
            throw new RuntimeException(
                'OWASYS_REGISTRY_SEED_MISSING:'
                . $this->relativeFromOpusRoot($seedFile)
            );
        }

        $seed = json_decode(
            (string) file_get_contents($seedFile),
            true
        );

        if (
            !is_array($seed)
            || ($seed['contract'] ?? null) !== 'OWASYS_REGISTRY_SEED_V1'
        ) {
            throw new RuntimeException(
                'OWASYS_REGISTRY_SEED_INVALID:'
                . $this->relativeFromOpusRoot($seedFile)
            );
        }

        $imported = 0;

        foreach ((array) ($seed['applications'] ?? []) as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException(
                    'OWASYS_REGISTRY_SEED_ENTRY_INVALID'
                );
            }

            $this->upsertApplication($db, $entry, 'seed');
            $imported++;
        }

        return $imported;
    }

    private function importDiscoveredSites(SQLite3 $db): int
    {
        $pattern = rtrim($this->opusRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'sites'
            . DIRECTORY_SEPARATOR . '*'
            . DIRECTORY_SEPARATOR . 'config'
            . DIRECTORY_SEPARATOR . 'site.json';

        $imported = 0;

        foreach (glob($pattern) ?: [] as $siteJsonFile) {
            if (!is_string($siteJsonFile) || !is_file($siteJsonFile)) {
                continue;
            }

            $site = json_decode(
                (string) file_get_contents($siteJsonFile),
                true
            );

            if (!is_array($site)) {
                throw new RuntimeException(
                    'OWASYS_REGISTRY_DISCOVERED_SITE_INVALID:'
                    . $this->relativeFromOpusRoot($siteJsonFile)
                );
            }

            $contract = (string) ($site['contract'] ?? '');

            if (!in_array(
                $contract,
                [
                    'OPUS_SITE_APPLICATION_TREE_V2',
                    'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
                ],
                true
            )) {
                continue;
            }

            $siteDir = basename(dirname(dirname($siteJsonFile)));
            $siteId = (string) ($site['site_id'] ?? $siteDir);

            $this->upsertApplication(
                $db,
                [
                    'id' => $siteId,
                    'slug' => $siteId,
                    'name' => (string) (
                        $site['site_name'] ?? $siteId
                    ),
                    'kind' => (string) (
                        $site['kind'] ?? 'fullstack'
                    ),
                    'root_path' => 'sites/' . $siteDir,
                    'public_root' => (string) (
                        $site['public_root'] ?? 'www'
                    ),
                    'default_locale' => (string) (
                        $site['default_locale'] ?? 'fr'
                    ),
                    'theme' => (string) (
                        $site['theme'] ?? 'default'
                    ),
                    'status' => (string) (
                        $site['status'] ?? 'discovered'
                    ),
                    'blueprint' => (string) (
                        $site['blueprint'] ?? 'unknown'
                    ),
                    'generated_by' => (string) (
                        $site['generated_by'] ?? 'manual'
                    ),
                    'role' => (string) (
                        $site['role']
                        ?? 'standard-opus-application'
                    ),
                ],
                'discovered'
            );

            $imported++;
        }

        return $imported;
    }

    /** @param array<string,mixed> $entry */
    private function upsertApplication(
        SQLite3 $db,
        array $entry,
        string $source
    ): void {
        $normalized = $this->normalizeEntry($entry, $source);
        $payload = json_encode(
            $entry,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );
        $now = gmdate('c');

        $sql = <<<'SQL'
INSERT INTO owasys_applications (
    id, slug, name, kind, root_path, public_root, default_locale,
    theme, status, blueprint, generated_by, role, source,
    payload_json, created_at, updated_at
) VALUES (
    :id, :slug, :name, :kind, :root_path, :public_root,
    :default_locale, :theme, :status, :blueprint, :generated_by,
    :role, :source, :payload_json, :created_at, :updated_at
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
            throw new RuntimeException(
                'OWASYS_REGISTRY_UPSERT_PREPARE_FAILED:'
                . $normalized['id']
            );
        }

        foreach ($normalized as $key => $value) {
            $stmt->bindValue(
                ':' . $key,
                $value,
                SQLITE3_TEXT
            );
        }

        $stmt->bindValue(':payload_json', $payload, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', $now, SQLITE3_TEXT);
        $stmt->bindValue(':updated_at', $now, SQLITE3_TEXT);

        $result = $stmt->execute();

        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }

        $stmt->close();
    }

    /**
     * @param array<string,mixed> $entry
     * @return array<string,string>
     */
    private function normalizeEntry(
        array $entry,
        string $source
    ): array {
        $id = trim(
            (string) ($entry['id'] ?? $entry['site_id'] ?? '')
        );

        if (
            $id === ''
            || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $id) !== 1
        ) {
            throw new RuntimeException(
                'OWASYS_REGISTRY_APPLICATION_ID_INVALID:' . $id
            );
        }

        $kind = strtolower(
            trim((string) ($entry['kind'] ?? 'fullstack'))
        );

        if (!in_array($kind, self::ALLOWED_KINDS, true)) {
            throw new RuntimeException(
                'OWASYS_REGISTRY_APPLICATION_KIND_INVALID:'
                . $id . ':' . $kind
            );
        }

        $rootPath = trim(
            str_replace(
                '\\',
                '/',
                (string) ($entry['root_path'] ?? ('sites/' . $id))
            ),
            '/'
        );
        $this->assertSafeRelativePath(
            $rootPath,
            'OWASYS_REGISTRY_APPLICATION_ROOT_INVALID:' . $id
        );

        if (!str_starts_with($rootPath, 'sites/')) {
            throw new RuntimeException(
                'OWASYS_REGISTRY_APPLICATION_ROOT_OUT_OF_SCOPE:'
                . $id . ':' . $rootPath
            );
        }

        $publicRoot = trim(
            str_replace(
                '\\',
                '/',
                (string) ($entry['public_root'] ?? 'www')
            ),
            '/'
        );
        $this->assertSafeRelativePath(
            $publicRoot,
            'OWASYS_REGISTRY_PUBLIC_ROOT_INVALID:' . $id
        );

        return [
            'id' => $id,
            'slug' => (string) ($entry['slug'] ?? $id),
            'name' => (string) (
                $entry['name'] ?? $entry['site_name'] ?? $id
            ),
            'kind' => $kind,
            'root_path' => $rootPath,
            'public_root' => $publicRoot,
            'default_locale' => (string) (
                $entry['default_locale'] ?? 'fr'
            ),
            'theme' => (string) ($entry['theme'] ?? 'default'),
            'status' => (string) (
                $entry['status'] ?? 'discovered'
            ),
            'blueprint' => (string) (
                $entry['blueprint'] ?? 'unknown'
            ),
            'generated_by' => (string) (
                $entry['generated_by'] ?? 'unknown'
            ),
            'role' => (string) (
                $entry['role']
                ?? 'standard-opus-application'
            ),
            'source' => $source,
        ];
    }

    /** @param array<string,mixed> $row */
    private function entryFromRow(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'kind' => (string) ($row['kind'] ?? ''),
            'root_path' => (string) ($row['root_path'] ?? ''),
            'public_root' => (string) ($row['public_root'] ?? ''),
            'default_locale' => (string) (
                $row['default_locale'] ?? ''
            ),
            'theme' => (string) ($row['theme'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'blueprint' => (string) ($row['blueprint'] ?? ''),
            'generated_by' => (string) (
                $row['generated_by'] ?? ''
            ),
            'role' => (string) ($row['role'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function countApplications(SQLite3 $db): int
    {
        $count = $db->querySingle(
            'SELECT COUNT(*) FROM owasys_applications'
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    private function applicationExists(
        SQLite3 $db,
        string $applicationId
    ): bool {
        $stmt = $db->prepare(
            'SELECT id FROM owasys_applications '
            . 'WHERE id = :id LIMIT 1'
        );

        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException(
                'OWASYS_REGISTRY_APPLICATION_EXISTS_PREPARE_FAILED'
            );
        }

        $stmt->bindValue(':id', $applicationId, SQLITE3_TEXT);
        $result = $stmt->execute();

        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException(
                'OWASYS_REGISTRY_APPLICATION_EXISTS_QUERY_FAILED'
            );
        }

        $row = $result->fetchArray(SQLITE3_ASSOC);
        $result->finalize();
        $stmt->close();

        return is_array($row);
    }

    /** @return array<string,mixed>|null */
    private function getContextValue(
        SQLite3 $db,
        string $key
    ): ?array {
        $this->assertRuntimeKey($key);
        $stmt = $db->prepare(
            'SELECT value_json FROM owasys_runtime_context '
            . 'WHERE key = :key LIMIT 1'
        );

        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException(
                'OWASYS_RUNTIME_CONTEXT_READ_PREPARE_FAILED:' . $key
            );
        }

        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $result = $stmt->execute();

        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException(
                'OWASYS_RUNTIME_CONTEXT_READ_QUERY_FAILED:' . $key
            );
        }

        $row = $result->fetchArray(SQLITE3_ASSOC);
        $result->finalize();
        $stmt->close();

        if (!is_array($row)) {
            return null;
        }

        $decoded = json_decode(
            (string) ($row['value_json'] ?? ''),
            true
        );

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $value */
    private function setContextValue(
        SQLite3 $db,
        string $key,
        array $value
    ): void {
        $this->assertRuntimeKey($key);
        $json = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );

        $stmt = $db->prepare(
            'INSERT INTO owasys_runtime_context '
            . '(key, value_json, updated_at) '
            . 'VALUES (:key, :value_json, :updated_at) '
            . 'ON CONFLICT(key) DO UPDATE SET '
            . 'value_json = excluded.value_json, '
            . 'updated_at = excluded.updated_at'
        );

        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException(
                'OWASYS_RUNTIME_CONTEXT_WRITE_PREPARE_FAILED:' . $key
            );
        }

        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value_json', $json, SQLITE3_TEXT);
        $stmt->bindValue(':updated_at', gmdate('c'), SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }

        $stmt->close();
    }

    private function deleteContextValue(
        SQLite3 $db,
        string $key
    ): void {
        $this->assertRuntimeKey($key);
        $stmt = $db->prepare(
            'DELETE FROM owasys_runtime_context WHERE key = :key'
        );

        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException(
                'OWASYS_RUNTIME_CONTEXT_DELETE_PREPARE_FAILED:' . $key
            );
        }

        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }

        $stmt->close();
    }

    /** @param array<string,mixed> $payload */
    private function recordEvent(
        SQLite3 $db,
        string $applicationId,
        string $eventType,
        array $payload
    ): void {
        if (
            preg_match('/^[a-z][a-z0-9_:-]*$/', $eventType) !== 1
        ) {
            throw new RuntimeException(
                'OWASYS_RUNTIME_EVENT_TYPE_INVALID:' . $eventType
            );
        }

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );
        $stmt = $db->prepare(
            'INSERT INTO owasys_application_events '
            . '(application_id, event_type, payload_json, created_at) '
            . 'VALUES (:application_id, :event_type, '
            . ':payload_json, :created_at)'
        );

        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException(
                'OWASYS_RUNTIME_EVENT_INSERT_PREPARE_FAILED:'
                . $eventType
            );
        }

        $stmt->bindValue(
            ':application_id',
            $applicationId,
            SQLITE3_TEXT
        );
        $stmt->bindValue(':event_type', $eventType, SQLITE3_TEXT);
        $stmt->bindValue(':payload_json', $json, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', gmdate('c'), SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }

        $stmt->close();
    }

    private function safeEventApplicationId(
        SQLite3 $db,
        string $applicationId
    ): string {
        if (
            $applicationId !== ''
            && $this->applicationExists($db, $applicationId)
        ) {
            return $applicationId;
        }

        if (
            $this->applicationExists(
                $db,
                self::SYSTEM_APPLICATION_ID
            )
        ) {
            return self::SYSTEM_APPLICATION_ID;
        }

        throw new RuntimeException(
            'OWASYS_RUNTIME_EVENT_SYSTEM_APPLICATION_MISSING'
        );
    }

    private function assertRuntimeKey(string $key): void
    {
        if (
            preg_match('/^[a-z][a-z0-9_:-]*$/', $key) !== 1
        ) {
            throw new RuntimeException(
                'OWASYS_RUNTIME_CONTEXT_KEY_INVALID:' . $key
            );
        }
    }

    private function assertSafeRelativePath(
        string $path,
        string $error
    ): void {
        $normalized = trim(str_replace('\\', '/', $path), '/');

        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized) === 1
            || str_contains($normalized, '..')
        ) {
            throw new RuntimeException($error . ':' . $path);
        }
    }

    private function relativeFromOpusRoot(string $path): string
    {
        $root = rtrim(
            str_replace(
                '\\',
                '/',
                realpath($this->opusRoot) ?: $this->opusRoot
            ),
            '/'
        ) . '/';
        $normalized = str_replace(
            '\\',
            '/',
            realpath($path) ?: $path
        );

        return str_starts_with($normalized, $root)
            ? substr($normalized, strlen($root))
            : $normalized;
    }
}
