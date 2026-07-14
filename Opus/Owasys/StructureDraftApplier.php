<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

/**
 * Applies one previously prepared OWASYS structure draft to an OPUS site.
 *
 * The applier is deliberately explicit: it reads a SQLite draft, builds a write
 * plan, refuses collisions, writes only known OPUS state-first files, then
 * persists an apply result in the OWASYS runtime database.
 */
final class StructureDraftApplier
{
    public const RESULT_CONTRACT = 'OWASYS_STRUCTURE_DRAFT_APPLY_RESULT_V1';

    public function __construct(
        private readonly string $opusRoot,
        private readonly RegistryRepository $registryRepository,
        private readonly ApplicationInspector $inspector,
    ) {
        if (!class_exists(SQLite3::class)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLIER_SQLITE3_EXTENSION_MISSING');
        }
    }

    public static function forOpusRoot(string $opusRoot, RegistryRepository $registryRepository): self
    {
        return new self(
            rtrim($opusRoot, DIRECTORY_SEPARATOR),
            $registryRepository,
            ApplicationInspector::forOpusRoot($opusRoot),
        );
    }

    /**
     * @param array<string,mixed> $entry
     * @return array<string,mixed>
     */
    public function applyAddStateDraft(array $entry, int $draftId, ?string $actorId = null): array
    {
        if ($draftId < 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_ID_INVALID: ' . $draftId);
        }

        $draft = $this->draft($draftId);
        if ($draft === null) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_DRAFT_MISSING: ' . $draftId);
        }
        if (($draft['contract'] ?? null) !== StructureDraftRepository::ADD_STATE_DRAFT_CONTRACT || ($draft['draft_type'] ?? null) !== 'add_state') {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_DRAFT_CONTRACT_INVALID: ' . $draftId);
        }
        if (($draft['status'] ?? null) !== 'draft') {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_DRAFT_STATUS_INVALID: ' . (string) ($draft['status'] ?? ''));
        }

        $applicationId = (string) ($entry['id'] ?? '');
        if ($applicationId === '' || $applicationId !== (string) ($draft['application_id'] ?? '')) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_APPLICATION_MISMATCH');
        }

        $stateId = $this->stateId((string) ($draft['state_id'] ?? ''));
        $routePath = $this->routePath((string) ($draft['route_path'] ?? ''));
        $titleKey = $this->i18nKey((string) ($draft['title_key'] ?? ''));
        $eventName = $this->eventName((string) ($draft['event_name'] ?? ''));
        $siteRoot = $this->siteRoot($entry);
        $inspectionBefore = $this->inspector->inspectEntry($entry);
        $this->assertStateAndRouteAbsent($inspectionBefore, $stateId, $routePath);

        $siteFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
        $routesFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.json';
        $fsmFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'application.fsm.json';
        $legacyFsmFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'fsm.json';
        $viewRelative = 'application/states/' . $stateId . '/views/index.php';
        $templateRelative = 'application/states/' . $stateId . '/templates/index.score';
        $viewFile = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $viewRelative);
        $templateFile = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $templateRelative);
        $stateRoot = dirname(dirname($viewFile));

        if (is_dir($stateRoot) || is_file($viewFile) || is_file($templateFile)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_TARGET_COLLISION: ' . $stateId);
        }

        $site = $this->readJsonFile($siteFile, 'OWASYS_STRUCTURE_DRAFT_APPLY_SITE_JSON_INVALID');
        $routes = $this->readJsonFile($routesFile, 'OWASYS_STRUCTURE_DRAFT_APPLY_ROUTES_JSON_INVALID');
        $fsm = $this->readJsonFile($fsmFile, 'OWASYS_STRUCTURE_DRAFT_APPLY_FSM_JSON_INVALID');
        if (($site['contract'] ?? null) !== 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL' || ($site['states_root'] ?? null) !== 'application/states' || ($site['dispatch_model'] ?? null) !== 'state-first') {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SITE_CONTRACT_INVALID');
        }
        if (($routes['contract'] ?? null) !== 'OPUS_ROUTE_REGISTRY_V1' || ($routes['dispatch_model'] ?? null) !== 'state-first' || !is_array($routes['routes'] ?? null)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_ROUTES_CONTRACT_INVALID');
        }
        if (($fsm['contract'] ?? null) !== 'OPUS_APPLICATION_FSM_V1' || ($fsm['dispatch_model'] ?? null) !== 'state-first' || !is_array($fsm['states'] ?? null) || !is_array($fsm['transitions'] ?? null)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_FSM_CONTRACT_INVALID');
        }

        $order = 10;
        foreach ($routes['routes'] as $route) {
            if (is_array($route)) {
                $order = max($order, (int) ($route['order'] ?? 0));
            }
        }
        $order += 10;
        $label = $this->label($stateId);
        $initialState = (string) ($fsm['initial_state'] ?? 'home');
        if ($initialState === '') {
            $initialState = 'home';
        }

        $routes['routes'][] = [
            'id' => $stateId . '.index',
            'path' => $routePath,
            'state' => $stateId,
            'controller' => $stateId,
            'controller_legacy_alias' => true,
            'class' => null,
            'template' => $templateRelative,
            'view' => $viewRelative,
            'label' => $titleKey,
            'fsm_state' => $stateId,
            'dispatch_action' => 'render_route',
            'show_in_menu' => true,
            'order' => $order,
        ];
        $fsm['states'][] = [
            'id' => $stateId,
            'label' => $label,
            'state' => $stateId,
            'controller' => $stateId,
            'controller_legacy_alias' => true,
            'route' => $routePath,
            'view' => $viewRelative,
            'template' => $templateRelative,
            'dispatch' => [
                'action' => 'render_route',
                'target' => $stateId,
            ],
            'visual' => true,
        ];
        $fsm['transitions'][] = [
            'from' => $initialState,
            'event' => $eventName,
            'to' => $stateId,
            'guard' => 'route_exists',
            'action' => 'render_route',
            'dispatch' => [
                'action' => 'render_route',
                'target_state' => $stateId,
            ],
            'visual' => true,
        ];
        $fsm['transitions'][] = [
            'from' => $stateId,
            'event' => 'open_' . $initialState,
            'to' => $initialState,
            'guard' => 'route_exists',
            'action' => 'render_route',
            'dispatch' => [
                'action' => 'render_route',
                'target_state' => $initialState,
            ],
            'visual' => true,
        ];

        $written = [];
        $this->writeJsonFile($routesFile, $routes);
        $written[] = $this->relativeFromOpusRoot($routesFile);
        $this->writeJsonFile($fsmFile, $fsm);
        $written[] = $this->relativeFromOpusRoot($fsmFile);
        if (is_file($legacyFsmFile)) {
            $legacy = $this->legacyFsm($fsm);
            $this->writeJsonFile($legacyFsmFile, $legacy);
            $written[] = $this->relativeFromOpusRoot($legacyFsmFile);
        }
        $this->ensureDirectory(dirname($viewFile));
        $this->writeTextFile($viewFile, $this->viewSource($stateId, $titleKey));
        $written[] = $this->relativeFromOpusRoot($viewFile);
        $this->ensureDirectory(dirname($templateFile));
        $this->writeTextFile($templateFile, $this->templateSource($stateId, $titleKey));
        $written[] = $this->relativeFromOpusRoot($templateFile);

        $locales = array_values(array_filter((array) ($site['locales'] ?? [$site['default_locale'] ?? 'fr']), 'is_string'));
        if ($locales === []) {
            $locales = ['fr'];
        }
        foreach ($locales as $locale) {
            $localFile = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . $locale . '.php';
            $messages = is_file($localFile) ? require $localFile : [];
            $messages = is_array($messages) ? $messages : [];
            if (!isset($messages[$titleKey])) {
                $messages[$titleKey] = $label;
            }
            $this->ensureDirectory(dirname($localFile));
            $this->writeTextFile($localFile, "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($messages, true) . ";\n");
            $written[] = $this->relativeFromOpusRoot($localFile);
        }

        $inspectionAfter = $this->inspector->inspectEntry($entry);
        $result = [
            'contract' => self::RESULT_CONTRACT,
            'status' => 'applied',
            'draft_id' => $draftId,
            'application_id' => $applicationId,
            'state_id' => $stateId,
            'route_path' => $routePath,
            'title_key' => $titleKey,
            'event_name' => $eventName,
            'applied_at' => gmdate('c'),
            'applied_by' => (string) ($actorId ?? 'runtime'),
            'written' => array_values(array_unique($written)),
            'state_count' => (int) ($inspectionAfter['state_count'] ?? 0),
            'route_count' => (int) ($inspectionAfter['route_count'] ?? 0),
            'transition_count' => (int) ($inspectionAfter['transition_count'] ?? 0),
            'validation' => $inspectionAfter,
        ];
        $this->markApplied($draftId, $draft, $result);

        return $result;
    }

    /** @return array<string,mixed>|null */
    private function draft(int $draftId): ?array
    {
        $db = $this->openDatabase();
        try {
            $stmt = $db->prepare('SELECT id, payload_json, status FROM owasys_structure_drafts WHERE id = :id LIMIT 1');
            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_READ_PREPARE_FAILED');
            }
            $stmt->bindValue(':id', $draftId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_READ_QUERY_FAILED');
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
        $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
        $payload = is_array($payload) ? $payload : [];
        $payload['id'] = (int) ($row['id'] ?? $draftId);
        $payload['status'] = (string) ($row['status'] ?? ($payload['status'] ?? ''));
        return $payload;
    }

    /** @param array<string,mixed> $draft @param array<string,mixed> $result */
    private function markApplied(int $draftId, array $draft, array $result): void
    {
        $draft['status'] = 'applied';
        $draft['disk_mutation'] = true;
        $draft['applied_at'] = $result['applied_at'];
        $draft['applied_by'] = $result['applied_by'];
        $draft['apply_result'] = $result;
        $json = json_encode($draft, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $resultJson = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $db = $this->openDatabase();
        try {
            $stmt = $db->prepare('UPDATE owasys_structure_drafts SET status = :status, payload_json = :payload_json, updated_at = :updated_at WHERE id = :id');
            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_UPDATE_PREPARE_FAILED');
            }
            $stmt->bindValue(':status', 'applied', SQLITE3_TEXT);
            $stmt->bindValue(':payload_json', is_string($json) ? $json : '{}', SQLITE3_TEXT);
            $stmt->bindValue(':updated_at', (string) $result['applied_at'], SQLITE3_TEXT);
            $stmt->bindValue(':id', $draftId, SQLITE3_INTEGER);
            $sqliteResult = $stmt->execute();
            if ($sqliteResult instanceof SQLite3Result) {
                $sqliteResult->finalize();
            }
            $stmt->close();

            $context = $db->prepare('INSERT INTO owasys_runtime_context (key, value_json, updated_at) VALUES (:key, :value_json, :updated_at) ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = excluded.updated_at');
            if (!$context instanceof SQLite3Stmt) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_CONTEXT_PREPARE_FAILED');
            }
            $context->bindValue(':key', 'last_structure_apply', SQLITE3_TEXT);
            $context->bindValue(':value_json', is_string($resultJson) ? $resultJson : '{}', SQLITE3_TEXT);
            $context->bindValue(':updated_at', (string) $result['applied_at'], SQLITE3_TEXT);
            $contextResult = $context->execute();
            if ($contextResult instanceof SQLite3Result) {
                $contextResult->finalize();
            }
            $context->close();

            $event = $db->prepare('INSERT INTO owasys_application_events (application_id, event_type, payload_json, created_at) VALUES (:application_id, :event_type, :payload_json, :created_at)');
            if (!$event instanceof SQLite3Stmt) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_EVENT_PREPARE_FAILED');
            }
            $event->bindValue(':application_id', (string) $result['application_id'], SQLITE3_TEXT);
            $event->bindValue(':event_type', 'apply_structure_draft', SQLITE3_TEXT);
            $event->bindValue(':payload_json', is_string($resultJson) ? $resultJson : '{}', SQLITE3_TEXT);
            $event->bindValue(':created_at', (string) $result['applied_at'], SQLITE3_TEXT);
            $eventResult = $event->execute();
            if ($eventResult instanceof SQLite3Result) {
                $eventResult->finalize();
            }
            $event->close();
        } finally {
            $db->close();
        }
    }

    private function openDatabase(): SQLite3
    {
        $db = new SQLite3($this->registryRepository->databasePath());
        $db->enableExceptions(true);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA foreign_keys = ON');
        return $db;
    }

    /** @param array<string,mixed> $entry */
    private function siteRoot(array $entry): string
    {
        $relative = trim(str_replace('\\', '/', (string) ($entry['root_path'] ?? '')), '/');
        if ($relative === '' || !str_starts_with($relative, 'sites/') || str_contains($relative, '..')) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_ROOT_PATH_INVALID: ' . $relative);
        }
        $root = rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_dir($root)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SITE_ROOT_MISSING: ' . $relative);
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

    /** @param array<string,mixed> $value */
    private function writeJsonFile(string $file, array $value): void
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_JSON_ENCODE_FAILED: ' . $this->relativeFromOpusRoot($file));
        }
        $this->writeTextFile($file, $encoded . "\n");
    }

    private function writeTextFile(string $file, string $content): void
    {
        $this->ensureDirectory(dirname($file));
        if (file_put_contents($file, $content) === false) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_WRITE_FAILED: ' . $this->relativeFromOpusRoot($file));
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_DIRECTORY_CREATE_FAILED: ' . $this->relativeFromOpusRoot($directory));
        }
    }

    /** @param array<string,mixed> $inspection */
    private function assertStateAndRouteAbsent(array $inspection, string $stateId, string $routePath): void
    {
        foreach ((array) ($inspection['states'] ?? []) as $state) {
            if (is_array($state) && (string) ($state['id'] ?? '') === $stateId) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_STATE_ALREADY_EXISTS: ' . $stateId);
            }
        }
        foreach ((array) ($inspection['routes'] ?? []) as $route) {
            if (is_array($route) && (string) ($route['path'] ?? '') === $routePath) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_ROUTE_ALREADY_EXISTS: ' . $routePath);
            }
        }
    }

    /** @param array<string,mixed> $fsm @return array<string,mixed> */
    private function legacyFsm(array $fsm): array
    {
        $states = [];
        foreach ((array) ($fsm['states'] ?? []) as $state) {
            if (is_array($state) && (string) ($state['id'] ?? '') !== '') {
                $states[] = [
                    'id' => strtoupper((string) $state['id']),
                    'state' => (string) ($state['state'] ?? $state['id']),
                    'controller' => (string) ($state['controller'] ?? $state['id']),
                    'route' => (string) ($state['route'] ?? ''),
                ];
            }
        }
        $transitions = [];
        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (is_array($transition)) {
                $transitions[] = [
                    'from' => strtoupper((string) ($transition['from'] ?? '')),
                    'event' => (string) ($transition['event'] ?? ''),
                    'to' => strtoupper((string) ($transition['to'] ?? '')),
                ];
            }
        }
        return [
            'contract' => 'OPUS_FSM_REGISTRY_V1',
            'source_of_truth' => 'config/application.fsm.json',
            'initial_state' => strtoupper((string) ($fsm['initial_state'] ?? 'home')),
            'states' => $states,
            'transitions' => $transitions,
        ];
    }

    private function viewSource(string $stateId, string $titleKey): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'state' => '" . $stateId . "',\n    'title_key' => '" . $titleKey . "',\n    'badge_key' => '" . $titleKey . "',\n    'summary_key' => 'state.default.summary',\n    'contracts' => [\n        'OPUS_APPLICATION_STATE_V1',\n    ],\n    'action_keys' => [],\n];\n";
    }

    private function templateSource(string $stateId, string $titleKey): string
    {
        return '<section data-opus-state="' . $stateId . '">{{ ' . $titleKey . ' }}</section>' . "\n";
    }

    private function label(string $stateId): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $stateId));
    }

    private function stateId(string $stateId): string
    {
        $stateId = strtolower(trim($stateId));
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $stateId) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_STATE_ID_INVALID: ' . $stateId);
        }
        return $stateId;
    }

    private function routePath(string $routePath): string
    {
        $routePath = '/' . trim(str_replace('\\', '/', $routePath), '/');
        if ($routePath === '/' || str_contains($routePath, '..') || preg_match('#^/[a-z0-9][a-z0-9/_-]{0,120}$#', $routePath) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_ROUTE_PATH_INVALID: ' . $routePath);
        }
        return $routePath;
    }

    private function i18nKey(string $key): string
    {
        $key = trim($key);
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $key) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_I18N_KEY_INVALID: ' . $key);
        }
        return $key;
    }

    private function eventName(string $eventName): string
    {
        $eventName = trim($eventName);
        if (preg_match('/^[a-z][a-z0-9_:-]*$/', $eventName) !== 1) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_EVENT_NAME_INVALID: ' . $eventName);
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
