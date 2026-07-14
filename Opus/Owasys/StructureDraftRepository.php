<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

/**
 * Runtime-only draft repository for OWASYS structure mutations.
 *
 * Drafts are persisted in the OWASYS SQLite database and never mutate the
 * selected OPUS application on disk. A later explicit apply step will be
 * responsible for writing files after validation.
 */
final class StructureDraftRepository
{
    public const CONTRACT = 'OWASYS_STRUCTURE_DRAFTS_SQLITE_V1';
    public const ADD_STATE_DRAFT_CONTRACT = 'OWASYS_STRUCTURE_ADD_STATE_DRAFT_V1';

    public function __construct(private readonly RegistryRepository $registryRepository)
    {
        if (!class_exists(SQLite3::class)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_SQLITE3_EXTENSION_MISSING');
        }
    }

    public static function forRegistry(RegistryRepository $registryRepository): self
    {
        return new self($registryRepository);
    }

    /**
     * Prepare a safe add-state draft without touching the selected application.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $inspection
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public function prepareAddStateDraft(array $entry, array $inspection, array $request, ?string $actorId = null): array
    {
        if (($inspection['inspection_contract'] ?? null) !== ApplicationInspector::CONTRACT) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_INSPECTION_CONTRACT_INVALID');
        }

        $applicationId = (string) ($inspection['site_id'] ?? ($entry['id'] ?? ''));
        if ($applicationId === '' || $applicationId !== (string) ($entry['id'] ?? $applicationId)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLICATION_ID_INVALID');
        }

        $stateId = $this->stateId((string) ($request['state_id'] ?? ''));
        $routePath = $this->routePath((string) ($request['route_path'] ?? ''));
        $titleKey = $this->i18nKey((string) ($request['title_key'] ?? ('state.' . $stateId . '.title')));
        $eventName = $this->eventName((string) ($request['event_name'] ?? ('open_' . $stateId)));

        $this->assertStateDoesNotExist($inspection, $stateId);
        $this->assertRouteDoesNotExist($inspection, $routePath);

        $draft = [
            'contract' => self::ADD_STATE_DRAFT_CONTRACT,
            'status' => 'draft',
            'draft_type' => 'add_state',
            'application_id' => $applicationId,
            'state_id' => $stateId,
            'route_path' => $routePath,
            'title_key' => $titleKey,
            'event_name' => $eventName,
            'target_directory' => 'application/states/' . $stateId,
            'target_view' => 'application/states/' . $stateId . '/views/index.php',
            'target_template' => 'application/states/' . $stateId . '/templates/index.score',
            'fsm_contract' => (string) ($inspection['fsm_contract'] ?? ''),
            'created_by' => (string) ($actorId ?? 'runtime'),
            'created_at' => gmdate('c'),
            'disk_mutation' => false,
        ];

        $db = $this->open();
        try {
            $this->ensureSchema($db);
            $this->assertApplicationExists($db, $applicationId);
            $draftId = $this->insertDraft($db, $draft);
            $draft['id'] = $draftId;
            $this->setContextValue($db, 'last_structure_draft', $draft);
            $this->recordEvent($db, $applicationId, 'draft_add_state', [
                'actor_id' => $actorId,
                'draft' => $draft,
            ]);
        } finally {
            $db->close();
        }

        return $draft;
    }

    /** @return list<array<string,mixed>> */
    public function recentDrafts(string $applicationId, int $limit = 8): array
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $applicationId) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLICATION_ID_INVALID: ' . $applicationId);
        }
        if ($limit < 1 || $limit > 50) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_LIMIT_INVALID: ' . $limit);
        }

        $db = $this->open();
        try {
            $this->ensureSchema($db);
            $stmt = $db->prepare('SELECT id, draft_type, status, payload_json, created_by, created_at, updated_at FROM owasys_structure_drafts WHERE application_id = :application_id ORDER BY id DESC LIMIT :limit');
            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_RECENT_PREPARE_FAILED');
            }
            $stmt->bindValue(':application_id', $applicationId, SQLITE3_TEXT);
            $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_RECENT_QUERY_FAILED');
            }
            $drafts = [];
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
                $drafts[] = array_merge(is_array($payload) ? $payload : [], [
                    'id' => (int) ($row['id'] ?? 0),
                    'draft_type' => (string) ($row['draft_type'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'created_by' => (string) ($row['created_by'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                ]);
            }
            $result->finalize();
            $stmt->close();
        } finally {
            $db->close();
        }

        return $drafts;
    }

    private function open(): SQLite3
    {
        $path = $this->registryRepository->databasePath();
        $parent = dirname($path);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_DATABASE_DIRECTORY_CREATE_FAILED: ' . $this->registryRepository->relativeDatabasePath());
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
CREATE TABLE IF NOT EXISTS owasys_structure_drafts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id TEXT NOT NULL,
    draft_type TEXT NOT NULL,
    status TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY(application_id) REFERENCES owasys_applications(id) ON DELETE CASCADE
)
SQL);
    }

    /** @param array<string,mixed> $draft */
    private function insertDraft(SQLite3 $db, array $draft): int
    {
        $payload = json_encode($draft, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO owasys_structure_drafts (application_id, draft_type, status, payload_json, created_by, created_at, updated_at) VALUES (:application_id, :draft_type, :status, :payload_json, :created_by, :created_at, :updated_at)');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_INSERT_PREPARE_FAILED');
        }
        $stmt->bindValue(':application_id', (string) $draft['application_id'], SQLITE3_TEXT);
        $stmt->bindValue(':draft_type', (string) $draft['draft_type'], SQLITE3_TEXT);
        $stmt->bindValue(':status', (string) $draft['status'], SQLITE3_TEXT);
        $stmt->bindValue(':payload_json', is_string($payload) ? $payload : '{}', SQLITE3_TEXT);
        $stmt->bindValue(':created_by', (string) $draft['created_by'], SQLITE3_TEXT);
        $stmt->bindValue(':created_at', (string) $draft['created_at'], SQLITE3_TEXT);
        $stmt->bindValue(':updated_at', (string) $draft['created_at'], SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }
        $stmt->close();
        return (int) $db->lastInsertRowID();
    }

    /** @param array<string,mixed> $payload */
    private function recordEvent(SQLite3 $db, string $applicationId, string $eventType, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO owasys_application_events (application_id, event_type, payload_json, created_at) VALUES (:application_id, :event_type, :payload_json, :created_at)');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_EVENT_INSERT_PREPARE_FAILED');
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

    /** @param array<string,mixed> $value */
    private function setContextValue(SQLite3 $db, string $key, array $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO owasys_runtime_context (key, value_json, updated_at) VALUES (:key, :value_json, :updated_at) ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = excluded.updated_at');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_CONTEXT_WRITE_PREPARE_FAILED');
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

    private function assertApplicationExists(SQLite3 $db, string $applicationId): void
    {
        $stmt = $db->prepare('SELECT id FROM owasys_applications WHERE id = :id LIMIT 1');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLICATION_EXISTS_PREPARE_FAILED');
        }
        $stmt->bindValue(':id', $applicationId, SQLITE3_TEXT);
        $result = $stmt->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLICATION_EXISTS_QUERY_FAILED');
        }
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $result->finalize();
        $stmt->close();
        if (!is_array($row)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLICATION_UNKNOWN: ' . $applicationId);
        }
    }

    /** @param array<string,mixed> $inspection */
    private function assertStateDoesNotExist(array $inspection, string $stateId): void
    {
        foreach ((array) ($inspection['states'] ?? []) as $state) {
            if (is_array($state) && (string) ($state['id'] ?? '') === $stateId) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_STATE_ALREADY_EXISTS: ' . $stateId);
            }
        }
    }

    /** @param array<string,mixed> $inspection */
    private function assertRouteDoesNotExist(array $inspection, string $routePath): void
    {
        foreach ((array) ($inspection['routes'] ?? []) as $route) {
            if (is_array($route) && (string) ($route['path'] ?? '') === $routePath) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_ROUTE_ALREADY_EXISTS: ' . $routePath);
            }
        }
    }

    private function stateId(string $stateId): string
    {
        $stateId = strtolower(trim($stateId));
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $stateId) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_STATE_ID_INVALID: ' . $stateId);
        }
        return $stateId;
    }

    private function routePath(string $routePath): string
    {
        $routePath = '/' . trim(str_replace('\\', '/', $routePath), '/');
        if ($routePath === '/' || str_contains($routePath, '..') || preg_match('#^/[a-z0-9][a-z0-9/_-]{0,120}$#', $routePath) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_ROUTE_PATH_INVALID: ' . $routePath);
        }
        return $routePath;
    }

    private function i18nKey(string $key): string
    {
        $key = trim($key);
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $key) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_I18N_KEY_INVALID: ' . $key);
        }
        return $key;
    }

    private function eventName(string $eventName): string
    {
        $eventName = trim($eventName);
        if (preg_match('/^[a-z][a-z0-9_:-]*$/', $eventName) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_EVENT_NAME_INVALID: ' . $eventName);
        }
        return $eventName;
    }
}
