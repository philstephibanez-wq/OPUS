<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/**
 * Builds a read-only write plan for one OWASYS structure draft.
 *
 * The planner never writes to disk and never updates SQLite. It is used before
 * apply so the UI can show exactly which state-first OPUS files are expected to
 * be created or updated, and whether a collision would block the mutation.
 */
final class StructureDraftWritePlanner
{
    public const CONTRACT = 'OWASYS_STRUCTURE_DRAFT_WRITE_PLAN_V1';

    public function __construct(
        private readonly string $opusRoot,
        private readonly ApplicationInspector $inspector,
    ) {
    }

    public static function forOpusRoot(string $opusRoot): self
    {
        return new self(
            rtrim($opusRoot, DIRECTORY_SEPARATOR),
            ApplicationInspector::forOpusRoot($opusRoot),
        );
    }

    /**
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $draft
     * @return array<string,mixed>
     */
    public function planAddStateDraft(array $entry, array $draft): array
    {
        if (($draft['contract'] ?? null) !== StructureDraftRepository::ADD_STATE_DRAFT_CONTRACT || ($draft['draft_type'] ?? null) !== 'add_state') {
            throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_DRAFT_CONTRACT_INVALID');
        }
        if (($draft['status'] ?? null) !== 'draft') {
            throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_DRAFT_STATUS_INVALID: ' . (string) ($draft['status'] ?? ''));
        }

        $applicationId = (string) ($entry['id'] ?? '');
        if ($applicationId === '' || $applicationId !== (string) ($draft['application_id'] ?? '')) {
            throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_APPLICATION_MISMATCH');
        }

        $stateId = $this->stateId((string) ($draft['state_id'] ?? ''));
        $routePath = $this->routePath((string) ($draft['route_path'] ?? ''));
        $titleKey = $this->i18nKey((string) ($draft['title_key'] ?? ''));
        $eventName = $this->eventName((string) ($draft['event_name'] ?? ''));
        $siteRoot = $this->siteRoot($entry);
        $inspection = $this->inspector->inspectEntry($entry);
        $siteFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
        $site = $this->readJsonFile($siteFile, 'OWASYS_STRUCTURE_WRITE_PLAN_SITE_JSON_INVALID');
        if (($site['contract'] ?? null) !== 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL' || ($site['states_root'] ?? null) !== 'application/states' || ($site['dispatch_model'] ?? null) !== 'state-first') {
            throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SITE_CONTRACT_INVALID');
        }

        $locales = array_values(array_filter((array) ($site['locales'] ?? [$site['default_locale'] ?? 'fr']), 'is_string'));
        if ($locales === []) {
            $locales = ['fr'];
        }

        $plannedFiles = [
            $this->filePlan($siteRoot, 'config/routes.json', 'update', true),
            $this->filePlan($siteRoot, 'config/application.fsm.json', 'update', true),
            $this->filePlan($siteRoot, 'config/fsm.json', is_file($siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'fsm.json') ? 'update' : 'create', false),
            $this->filePlan($siteRoot, 'application/states/' . $stateId . '/views/index.php', 'create', true),
            $this->filePlan($siteRoot, 'application/states/' . $stateId . '/templates/index.score', 'create', true),
        ];
        foreach ($locales as $locale) {
            $plannedFiles[] = $this->filePlan($siteRoot, 'application/default/local/' . $locale . '.php', is_file($siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . $locale . '.php') ? 'update' : 'create', false);
        }

        $collisions = [];
        foreach ((array) ($inspection['states'] ?? []) as $state) {
            if (is_array($state) && (string) ($state['id'] ?? '') === $stateId) {
                $collisions[] = 'state:' . $stateId;
            }
        }
        foreach ((array) ($inspection['routes'] ?? []) as $route) {
            if (is_array($route) && (string) ($route['path'] ?? '') === $routePath) {
                $collisions[] = 'route:' . $routePath;
            }
        }
        foreach ($plannedFiles as $file) {
            if (is_array($file) && ($file['operation'] ?? null) === 'create' && ($file['exists'] ?? false) === true) {
                $collisions[] = 'file:' . (string) ($file['path'] ?? '');
            }
        }

        return [
            'contract' => self::CONTRACT,
            'status' => $collisions === [] ? 'ready' : 'blocked',
            'draft_id' => (int) ($draft['id'] ?? 0),
            'application_id' => $applicationId,
            'state_id' => $stateId,
            'route_path' => $routePath,
            'title_key' => $titleKey,
            'event_name' => $eventName,
            'disk_mutation' => false,
            'collision_count' => count($collisions),
            'collisions' => $collisions,
            'files' => $plannedFiles,
        ];
    }

    /** @return array<string,mixed> */
    private function filePlan(string $siteRoot, string $relativePath, string $operation, bool $blocksOnExisting): array
    {
        $absolute = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $exists = file_exists($absolute);
        return [
            'path' => $relativePath,
            'operation' => $operation,
            'exists' => $exists,
            'blocks_on_existing' => $blocksOnExisting,
        ];
    }

    /** @param array<string,mixed> $entry */
    private function siteRoot(array $entry): string
    {
        $relative = trim(str_replace('\\', '/', (string) ($entry['root_path'] ?? '')), '/');
        if ($relative === '' || !str_starts_with($relative, 'sites/') || str_contains($relative, '..')) {
            throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_ROOT_PATH_INVALID: ' . $relative);
        }
        $root = rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_dir($root)) {
            throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SITE_ROOT_MISSING: ' . $relative);
        }
        return $root;
    }

    /** @return array<string,mixed> */
    private function readJsonFile(string $file, string $error): array
    {
        $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($decoded)) {
            throw new RuntimeException($error . ': ' . $this->relativeFromOpusRoot($file));
        }
        return $decoded;
    }

    private function stateId(string $stateId): string
    {
        $stateId = strtolower(trim($stateId));
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $stateId) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_STATE_ID_INVALID: ' . $stateId);
        }
        return $stateId;
    }

    private function routePath(string $routePath): string
    {
        $routePath = '/' . trim(str_replace('\\', '/', $routePath), '/');
        if ($routePath === '/' || str_contains($routePath, '..') || preg_match('#^/[a-z0-9][a-z0-9/_-]{0,120}$#', $routePath) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_ROUTE_PATH_INVALID: ' . $routePath);
        }
        return $routePath;
    }

    private function i18nKey(string $key): string
    {
        $key = trim($key);
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $key) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_I18N_KEY_INVALID: ' . $key);
        }
        return $key;
    }

    private function eventName(string $eventName): string
    {
        $eventName = trim($eventName);
        if (preg_match('/^[a-z][a-z0-9_:-]*$/', $eventName) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_EVENT_NAME_INVALID: ' . $eventName);
        }
        return $eventName;
    }

    private function relativeFromOpusRoot(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', realpath($this->opusRoot) ?: $this->opusRoot), '/') . '/';
        $normalized = str_replace('\\', '/', realpath($path) ?: $path);
        return str_starts_with($normalized, $root) ? substr($normalized, strlen($root)) : $normalized;
    }
}
