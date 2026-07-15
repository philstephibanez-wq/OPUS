<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

/**
 * Persists and validates server-side confirmations for OWASYS structure plans.
 *
 * A draft may only be applied after a ready write plan has been previewed by the
 * server. The confirmation stores a canonical hash of the previewed plan in the
 * OWASYS runtime SQLite context and the applier compares it to a freshly
 * recalculated plan before any disk mutation.
 */
final class StructureDraftPreviewConfirmation
{
    public const CONTRACT = 'OWASYS_STRUCTURE_DRAFT_PREVIEW_CONFIRMATION_V1';
    public const TTL_SECONDS = 900;

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    public static function persist(RegistryRepository $registryRepository, array $plan, ?string $actorId = null): array
    {
        if (($plan['contract'] ?? null) !== StructureDraftWritePlanner::CONTRACT) {
            throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CONFIRMATION_PLAN_CONTRACT_INVALID');
        }
        if (($plan['status'] ?? null) !== 'ready') {
            throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CONFIRMATION_PLAN_NOT_READY');
        }
        $draftId = (int) ($plan['draft_id'] ?? 0);
        $applicationId = (string) ($plan['application_id'] ?? '');
        $stateId = (string) ($plan['state_id'] ?? '');
        $routePath = (string) ($plan['route_path'] ?? '');
        $titleKey = (string) ($plan['title_key'] ?? '');
        $eventName = (string) ($plan['event_name'] ?? '');
        if ($draftId < 1 || $applicationId === '' || $stateId === '' || $routePath === '' || $titleKey === '' || $eventName === '') {
            throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CONFIRMATION_PLAN_TARGET_INVALID');
        }

        $now = gmdate('c');
        $confirmation = [
            'contract' => self::CONTRACT,
            'draft_id' => $draftId,
            'application_id' => $applicationId,
            'state_id' => $stateId,
            'route_path' => $routePath,
            'title_key' => $titleKey,
            'event_name' => $eventName,
            'status' => 'ready',
            'plan_hash' => self::planHash($plan),
            'previewed_at' => $now,
            'previewed_by' => (string) ($actorId ?? 'runtime'),
            'expires_at' => gmdate('c', time() + self::TTL_SECONDS),
        ];
        $json = json_encode($confirmation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $db = self::openDatabase($registryRepository);
        try {
            $stmt = $db->prepare('INSERT INTO owasys_runtime_context (key, value_json, updated_at) VALUES (:key, :value_json, :updated_at) ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = excluded.updated_at');
            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CONFIRMATION_WRITE_PREPARE_FAILED');
            }
            $stmt->bindValue(':key', self::key($draftId), SQLITE3_TEXT);
            $stmt->bindValue(':value_json', is_string($json) ? $json : '{}', SQLITE3_TEXT);
            $stmt->bindValue(':updated_at', $now, SQLITE3_TEXT);
            $result = $stmt->execute();
            if ($result instanceof SQLite3Result) {
                $result->finalize();
            }
            $stmt->close();
        } finally {
            $db->close();
        }
        return $confirmation;
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    public static function assertConfirmed(RegistryRepository $registryRepository, int $draftId, string $applicationId, array $plan): array
    {
        if ($draftId < 1 || $applicationId === '') {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_PREVIEW_TARGET_INVALID');
        }
        $confirmation = self::read($registryRepository, $draftId);
        if ($confirmation === null) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_PREVIEW_CONFIRMATION_MISSING');
        }
        if (($confirmation['contract'] ?? null) !== self::CONTRACT) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_PREVIEW_CONFIRMATION_CONTRACT_INVALID');
        }
        if ((int) ($confirmation['draft_id'] ?? 0) !== $draftId || (string) ($confirmation['application_id'] ?? '') !== $applicationId) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_PREVIEW_CONFIRMATION_TARGET_MISMATCH');
        }
        if ((string) ($confirmation['state_id'] ?? '') !== (string) ($plan['state_id'] ?? '')
            || (string) ($confirmation['route_path'] ?? '') !== (string) ($plan['route_path'] ?? '')
            || (string) ($confirmation['title_key'] ?? '') !== (string) ($plan['title_key'] ?? '')
            || (string) ($confirmation['event_name'] ?? '') !== (string) ($plan['event_name'] ?? '')) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_PREVIEW_CONFIRMATION_TARGET_CHANGED');
        }
        if (($confirmation['status'] ?? null) !== 'ready') {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_PREVIEW_CONFIRMATION_STATUS_INVALID');
        }
        $expiresAt = strtotime((string) ($confirmation['expires_at'] ?? ''));
        if ($expiresAt === false || $expiresAt < time()) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_PREVIEW_CONFIRMATION_EXPIRED');
        }
        if ((string) ($confirmation['plan_hash'] ?? '') !== self::planHash($plan)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_PREVIEW_CONFIRMATION_PLAN_CHANGED');
        }
        return $confirmation;
    }

    /** @param array<string,mixed> $plan */
    public static function planHash(array $plan): string
    {
        $canonicalFiles = [];
        foreach ((array) ($plan['files'] ?? []) as $file) {
            if (!is_array($file)) {
                continue;
            }
            $canonicalFiles[] = [
                'path' => (string) ($file['path'] ?? ''),
                'operation' => (string) ($file['operation'] ?? ''),
                'exists' => (bool) ($file['exists'] ?? false),
                'blocks_on_existing' => (bool) ($file['blocks_on_existing'] ?? false),
            ];
        }
        $canonical = [
            'contract' => (string) ($plan['contract'] ?? ''),
            'status' => (string) ($plan['status'] ?? ''),
            'draft_id' => (int) ($plan['draft_id'] ?? 0),
            'application_id' => (string) ($plan['application_id'] ?? ''),
            'state_id' => (string) ($plan['state_id'] ?? ''),
            'route_path' => (string) ($plan['route_path'] ?? ''),
            'title_key' => (string) ($plan['title_key'] ?? ''),
            'event_name' => (string) ($plan['event_name'] ?? ''),
            'disk_mutation' => (bool) ($plan['disk_mutation'] ?? true),
            'collision_count' => (int) ($plan['collision_count'] ?? 0),
            'collisions' => array_values(array_map('strval', (array) ($plan['collisions'] ?? []))),
            'files' => $canonicalFiles,
        ];
        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CONFIRMATION_HASH_ENCODE_FAILED');
        }
        return hash('sha256', $json);
    }

    public static function key(int $draftId): string
    {
        if ($draftId < 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CONFIRMATION_DRAFT_ID_INVALID');
        }
        return 'structure_preview:' . $draftId;
    }

    /** @return array<string,mixed>|null */
    private static function read(RegistryRepository $registryRepository, int $draftId): ?array
    {
        $db = self::openDatabase($registryRepository);
        try {
            $stmt = $db->prepare('SELECT value_json FROM owasys_runtime_context WHERE key = :key LIMIT 1');
            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CONFIRMATION_READ_PREPARE_FAILED');
            }
            $stmt->bindValue(':key', self::key($draftId), SQLITE3_TEXT);
            $result = $stmt->execute();
            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CONFIRMATION_READ_QUERY_FAILED');
            }
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $result->finalize();
            $stmt->close();
        } finally {
            $db->close();
        }
        if (!is_array($row)) {
            return null;
        }
        $payload = json_decode((string) ($row['value_json'] ?? '{}'), true);
        return is_array($payload) ? $payload : null;
    }

    private static function openDatabase(RegistryRepository $registryRepository): SQLite3
    {
        if (!class_exists(SQLite3::class)) {
            throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CONFIRMATION_SQLITE3_EXTENSION_MISSING');
        }
        $db = new SQLite3($registryRepository->databasePath());
        $db->enableExceptions(true);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA foreign_keys = ON');
        return $db;
    }
}
