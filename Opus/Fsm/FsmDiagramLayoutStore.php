<?php
declare(strict_types=1);

namespace Opus\Fsm;

use Opus\File\File;
use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Security\Csrf\CsrfTokenManager;
use RuntimeException;

/**
 * Portable file-backed persistence for FSM diagram geometry.
 *
 * Contract:
 * - the canonical FSM definition remains the sole semantic source of truth;
 * - persisted layout stores only presentation geometry and canvas metadata;
 * - V4 persists state coordinates, independently movable signal-card
 *   coordinates, non-semantic diagram-marker coordinates and relative cubic
 *   Bézier controls for transition presentation;
 * - canonical entry-state FSMs persist `begin` through ordinary state geometry
 *   and keep the independently positioned initial pseudostate marker explicit;
 * - when no layout exists, OPUS persists the computed automatic layout in DEV;
 * - when a layout exists, persisted state and transition geometry wins;
 * - new FSM states are auto-positioned and merged without discarding existing
 *   manual coordinates;
 * - a semantic state rename can prepare an optimistic identity refactor that
 *   preserves state, transition and finite-global-marker presentation;
 * - writes are allowed only under the PHP development server or when the
 *   explicit OPUS_FSM_LAYOUT_WRITE=1 override is present.
 */
final class FsmDiagramLayoutStore implements FsmDiagramLayoutStoreInterface
{
    public const CONTRACT = 'OPUS_FSM_DIAGRAM_LAYOUT_V4';
    private const LEGACY_CONTRACTS = [
        'OPUS_FSM_DIAGRAM_LAYOUT_V3',
        'OPUS_FSM_DIAGRAM_LAYOUT_V2',
        'OPUS_FSM_DIAGRAM_LAYOUT_V1',
    ];
    private const SAVE_STATE_ACTION = 'save-state';
    private const SAVE_SIGNAL_ACTION = 'save-signal';
    private const SAVE_MARKER_ACTION = 'save-marker';
    private const MAX_COORDINATE = 100000.0;
    private const MAX_GEOMETRY_BYTES = 262144;
    private const MAX_LAYOUT_BYTES = 1048576;
    private const MAX_SVG_PATH_BYTES = 8192;

    private readonly File $file;
    private readonly StructuredFileLoader $loader;
    private readonly Json $json;
    private readonly string $layoutKey;
    private ?array $resolved = null;

    private function __construct(
        private readonly string $siteRoot,
        private readonly string $fsmRelative,
        private readonly string $layoutRelative,
        private readonly string $layoutDirection,
        private readonly bool $writable
    ) {
        $this->file = File::instance();
        $this->loader = StructuredFileLoader::instance();
        $this->json = Json::instance();
        $this->layoutKey = hash(
            'sha256',
            $this->siteRoot . "\0" . $this->layoutRelative
        );
    }

    /**
     * Discover the application owning the currently rendered FSM from the
     * public document root. This keeps generated applications portable: the
     * layout file lives beside their own FSM configuration, not in OWASYS DB.
     *
     * @param array<string,mixed> $definition
     */
    public static function discover(
        array $definition,
        string $layoutDirection
    ): ?self {
        $contract = trim((string) ($definition['contract'] ?? ''));
        if (!in_array(
            $contract,
            [
                'OPUS_APPLICATION_FSM_V1',
                'OWASYS_NAVIGATION_FSM_V1',
                'OWASYS_BACK_FSM_V1',
            ],
            true
        )) {
            return null;
        }

        $documentRoot = trim(str_replace(
            '\\',
            '/',
            (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')
        ));
        if ($documentRoot === '') {
            return null;
        }
        $resolvedDocumentRoot = realpath($documentRoot);
        if (!is_string($resolvedDocumentRoot) || $resolvedDocumentRoot === '') {
            return null;
        }
        $siteRoot = dirname(str_replace('\\', '/', $resolvedDocumentRoot));
        $siteConfigPath = $siteRoot . '/config/site.json';
        $file = File::instance();
        if (!$file->exists($siteConfigPath)) {
            return null;
        }

        try {
            $site = StructuredFileLoader::instance()->read($siteConfigPath);
        } catch (\Throwable) {
            return null;
        }

        $fsmRelative = self::fsmRelativeForContract($site, $contract);
        if ($fsmRelative === null) {
            return null;
        }
        $fsmPath = $siteRoot . '/' . $fsmRelative;
        if (!$file->exists($fsmPath)) {
            return null;
        }
        try {
            $source = StructuredFileLoader::instance()->read($fsmPath);
        } catch (\Throwable) {
            return null;
        }
        if (($source['contract'] ?? null) !== $contract) {
            return null;
        }

        $layoutRelative = preg_replace(
            '/\.json$/D',
            '.layout.json',
            $fsmRelative
        );
        if (!is_string($layoutRelative)
            || $layoutRelative === $fsmRelative) {
            return null;
        }

        $direction = strtolower(trim($layoutDirection));
        if (!in_array($direction, ['horizontal', 'vertical'], true)) {
            throw new \InvalidArgumentException(
                'OPUS_FSM_DIAGRAM_LAYOUT_DIRECTION_INVALID:' . $direction
            );
        }

        $writeOverride = trim((string) getenv('OPUS_FSM_LAYOUT_WRITE'));
        $writable = PHP_SAPI === 'cli-server' || $writeOverride === '1';

        return new self(
            $siteRoot,
            $fsmRelative,
            $layoutRelative,
            $direction,
            $writable
        );
    }

    public static function forSource(
        string $siteRoot,
        string $fsmRelative,
        string $layoutDirection,
        bool $writable = false
    ): self {
        $resolvedRoot = realpath($siteRoot);
        if (!is_string($resolvedRoot) || !is_dir($resolvedRoot)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_SITE_ROOT_INVALID'
            );
        }
        $normalizedRoot = rtrim(str_replace('\\', '/', $resolvedRoot), '/');
        $relative = self::safeRelative($fsmRelative);
        if ($relative === ''
            || preg_match('/\.json$/D', $relative) !== 1
            || !File::instance()->exists($normalizedRoot . '/' . $relative)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_SOURCE_INVALID:' . $fsmRelative
            );
        }
        $layoutRelative = preg_replace(
            '/\.json$/D',
            '.layout.json',
            $relative
        );
        if (!is_string($layoutRelative)
            || $layoutRelative === $relative) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_PATH_INVALID:' . $relative
            );
        }
        $direction = strtolower(trim($layoutDirection));
        if (!in_array($direction, ['horizontal', 'vertical'], true)) {
            throw new \InvalidArgumentException(
                'OPUS_FSM_DIAGRAM_LAYOUT_DIRECTION_INVALID:' . $direction
            );
        }
        return new self(
            $normalizedRoot,
            $relative,
            $layoutRelative,
            $direction,
            $writable
        );
    }

    public function resolve(array $definition, array $automaticLayout): array
    {
        $automatic = $this->automaticLayout($definition, $automaticLayout);
        $layout = $this->loadPersisted($definition, $automatic);

        if ($layout === null) {
            $layout = $automatic;
            if ($this->writable) {
                $this->write($layout);
                $layout = $this->readLayout();
            }
        }

        $merged = $this->mergeCurrentStates(
            $definition,
            $layout,
            $automatic
        );
        $changed = $merged !== $layout;
        $layout = $merged;

        if ($this->matchesSaveRequest()) {
            if (!$this->writable) {
                throw new RuntimeException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_WRITE_FORBIDDEN'
                );
            }
            $layout = $this->applySaveRequest($definition, $layout);
            $changed = true;
        }

        if ($changed && $this->writable) {
            $this->write($layout);
            $layout = $this->readLayout();
        }

        $this->resolved = $layout;
        return $layout;
    }

    /**
     * Apply one transport-neutral presentation mutation. Trusted callers bind
     * this store explicitly with forSource(); no browser-authored path is used.
     *
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $automaticLayout
     * @param array<string,mixed> $command
     * @return array<string,mixed>
     */
    public function mutate(
        array $definition,
        array $automaticLayout,
        array $command
    ): array {
        if (!$this->writable) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_WRITE_FORBIDDEN'
            );
        }

        $automatic = $this->automaticLayout($definition, $automaticLayout);
        $layout = $this->loadPersisted($definition, $automatic);
        if ($layout === null) {
            $layout = $automatic;
        }
        $layout = $this->mergeCurrentStates(
            $definition,
            $layout,
            $automatic
        );

        $action = trim((string) ($command['action'] ?? ''));
        if ($action === self::SAVE_STATE_ACTION) {
            $stateId = trim((string) ($command['state_id'] ?? ''));
            $known = $this->definitionStateSet($definition);
            if ($stateId === '' || !isset($known[$stateId])) {
                throw new RuntimeException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_STATE_UNKNOWN:' . $stateId
                );
            }
            $layout['states'][$stateId] = [
                'x' => $this->coordinate($command['x'] ?? null, 'x'),
                'y' => $this->coordinate($command['y'] ?? null, 'y'),
            ];
        } elseif ($action === self::SAVE_MARKER_ACTION) {
            $markerId = trim((string) ($command['marker_id'] ?? ''));
            $knownMarkers = $this->definitionMarkerSet($definition);
            if ($markerId === '' || !isset($knownMarkers[$markerId])) {
                throw new RuntimeException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_MARKER_UNKNOWN:' . $markerId
                );
            }
        } elseif ($action !== self::SAVE_SIGNAL_ACTION) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_ACTION_INVALID:' . $action
            );
        }

        $geometry = $command['geometry'] ?? null;
        if (!is_array($geometry) || array_is_list($geometry)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_GEOMETRY_INVALID'
            );
        }
        $normalized = $this->normalizeGeometryPayload(
            $definition,
            $geometry
        );
        $layout['canvas'] = $normalized['canvas'];
        $layout['transitions'] = $normalized['transitions'];
        $layout['markers'] = $normalized['markers'];

        $this->write($layout);
        $this->resolved = $this->readLayout();
        return $this->resolved;
    }

    /**
     * Persist the exact server-rendered presentation geometry after a render.
     * Renderer output replaces stale transition presentation geometry so local
     * paths self-heal and V4 movable presentation geometry remains current.
     *
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $renderedGeometry
     */
    public function persistRenderedGeometry(
        array $definition,
        array $renderedGeometry
    ): void {
        if (!$this->writable || $this->resolved === null) {
            return;
        }

        $normalized = $this->normalizeGeometryPayload(
            $definition,
            $renderedGeometry
        );
        $layout = $this->resolved;
        $transitions = is_array($layout['transitions'] ?? null)
            ? $layout['transitions']
            : [];
        $markers = is_array($layout['markers'] ?? null)
            ? $layout['markers']
            : [];
        $changed = false;

        foreach ($normalized['transitions'] as $id => $geometry) {
            if (isset($transitions[$id])
                && $transitions[$id] === $geometry) {
                continue;
            }
            /*
             * The renderer validates current source/target anchors before it
             * exposes its snapshot. Replacing a stale stored entry here is
             * therefore the self-healing path for orphan transition geometry.
             */
            $transitions[$id] = $geometry;
            $changed = true;
        }

        foreach ($normalized['markers'] as $id => $geometry) {
            if (isset($markers[$id]) && $markers[$id] === $geometry) {
                continue;
            }
            $markers[$id] = $geometry;
            $changed = true;
        }

        if (!is_array($layout['canvas'] ?? null)) {
            $layout['canvas'] = $normalized['canvas'];
            $changed = true;
        }
        $layout['transitions'] = $transitions;
        $layout['markers'] = $markers;

        if (!$changed) {
            return;
        }

        $this->write($layout);
        $this->resolved = $this->readLayout();
    }

    public function prepareStateIdentityRefactor(
        array $oldDefinition,
        array $newDefinition,
        string $oldStateId,
        string $newStateId,
        string $newDefinitionSha256
    ): ?array {
        $oldStateId = trim($oldStateId);
        $newStateId = trim($newStateId);
        $newDefinitionSha256 = strtolower(trim($newDefinitionSha256));
        $oldStates = $this->definitionStateSet($oldDefinition);
        $newStates = $this->definitionStateSet($newDefinition);
        if ($oldStateId === ''
            || $newStateId === ''
            || !isset($oldStates[$oldStateId])
            || isset($oldStates[$newStateId])
            || isset($newStates[$oldStateId])
            || !isset($newStates[$newStateId])) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_STATE_REFACTOR_INVALID'
            );
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $newDefinitionSha256) !== 1) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_DEFINITION_HASH_INVALID'
            );
        }

        $path = $this->siteRoot . '/' . $this->layoutRelative;
        if (!$this->file->exists($path)) {
            return null;
        }
        $raw = $this->file->read($path, self::MAX_LAYOUT_BYTES);
        $layout = $this->readLayout();
        $contract = trim((string) ($layout['contract'] ?? ''));
        if (($contract !== self::CONTRACT
                && !in_array($contract, self::LEGACY_CONTRACTS, true))
            || ($layout['fsm_path'] ?? null) !== $this->fsmRelative
            || ($layout['layout_direction'] ?? null)
                !== $this->layoutDirection
            || !is_array($layout['states'] ?? null)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_REFACTOR_SOURCE_INVALID:'
                . $this->layoutRelative
            );
        }

        $statePositionMigrated = false;
        if (isset($layout['states'][$newStateId])) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_STATE_REFACTOR_CONFLICT:'
                . $newStateId
            );
        }
        if (is_array($layout['states'][$oldStateId] ?? null)) {
            $layout['states'][$newStateId] =
                $layout['states'][$oldStateId];
            unset($layout['states'][$oldStateId]);
            $statePositionMigrated = true;
        }

        $markers = is_array($layout['markers'] ?? null)
            ? $layout['markers']
            : [];
        $oldMarkerIds = $this->finiteGlobalMarkerIdsByTransition(
            $oldDefinition
        );
        $newMarkerIds = $this->finiteGlobalMarkerIdsByTransition(
            $newDefinition
        );
        $markerCountMigrated = 0;
        foreach ($oldMarkerIds as $transitionId => $oldMarkerId) {
            $newMarkerId = $newMarkerIds[$transitionId] ?? null;
            if (!is_string($newMarkerId)
                || $newMarkerId === $oldMarkerId
                || !is_array($markers[$oldMarkerId] ?? null)
                || isset($markers[$newMarkerId])) {
                continue;
            }
            $markers[$newMarkerId] = $markers[$oldMarkerId];
            ++$markerCountMigrated;
        }
        $layout['markers'] = $markers;
        $layout = $this->normalizeExistingPersisted(
            $newDefinition,
            $layout
        );
        $layout['contract'] = self::CONTRACT;
        $layout['fsm_path'] = $this->fsmRelative;
        $layout['definition_sha256'] = $newDefinitionSha256;
        $layout['layout_direction'] = $this->layoutDirection;

        return [
            'path' => $this->layoutRelative,
            'expected_sha256' => hash('sha256', $raw),
            'content' => $this->json->encode($layout, true),
            'state_position_migrated' => $statePositionMigrated,
            'marker_count_migrated' => $markerCountMigrated,
        ];
    }

    public function clientConfig(): array
    {
        if (!$this->writable || session_status() !== PHP_SESSION_ACTIVE) {
            return [
                'writable' => false,
                'layout_key' => $this->layoutKey,
                'csrf_token' => '',
                'layout_path' => $this->layoutRelative,
            ];
        }

        return [
            'writable' => true,
            'layout_key' => $this->layoutKey,
            'csrf_token' => (new CsrfTokenManager())->issue(
                $this->csrfScope()
            ),
            'layout_path' => $this->layoutRelative,
        ];
    }

    /** @param array<string,mixed> $site */
    private static function fsmRelativeForContract(
        array $site,
        string $contract
    ): ?string {
        $candidate = '';
        if ($contract === 'OPUS_APPLICATION_FSM_V1') {
            $candidate = (string) ($site['application_fsm'] ?? '');
        }
        if ($candidate === '') {
            $navigation = is_array($site['navigation'] ?? null)
                ? $site['navigation']
                : [];
            $candidate = (string) ($navigation['fsm'] ?? '');
        }

        $relative = self::safeRelative($candidate);
        return $relative === '' ? null : $relative;
    }

    private static function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")) {
            return '';
        }
        return $path;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $automaticLayout
     * @return array<string,mixed>
     */
    private function automaticLayout(
        array $definition,
        array $automaticLayout
    ): array {
        $positions = is_array($automaticLayout['positions'] ?? null)
            ? $automaticLayout['positions']
            : [];
        $states = [];
        foreach ((array) ($definition['states'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $id = trim((string) ($state['id'] ?? ''));
            $position = is_array($positions[$id] ?? null)
                ? $positions[$id]
                : null;
            if ($id === '' || !is_array($position)) {
                continue;
            }
            $states[$id] = [
                'x' => $this->coordinate($position['x'] ?? null, 'x'),
                'y' => $this->coordinate($position['y'] ?? null, 'y'),
            ];
        }

        return [
            'contract' => self::CONTRACT,
            'fsm_path' => $this->fsmRelative,
            'definition_sha256' => hash(
                'sha256',
                $this->file->read($this->siteRoot . '/' . $this->fsmRelative)
            ),
            'layout_direction' => $this->layoutDirection,
            'canvas' => [
                'width' => $this->positiveNumber(
                    $automaticLayout['width'] ?? 1.0,
                    'width'
                ),
                'height' => $this->positiveNumber(
                    $automaticLayout['height'] ?? 1.0,
                    'height'
                ),
            ],
            'states' => $states,
            'transitions' => [],
            'markers' => [],
        ];
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $automatic
     * @return array<string,mixed>|null
     */
    private function loadPersisted(
        array $definition,
        array $automatic
    ): ?array {
        $path = $this->siteRoot . '/' . $this->layoutRelative;
        if (!$this->file->exists($path)) {
            return null;
        }
        $layout = $this->readLayout();
        $contract = (string) ($layout['contract'] ?? '');
        if ($contract !== self::CONTRACT
            && !in_array($contract, self::LEGACY_CONTRACTS, true)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_CONTRACT_INVALID:'
                . $this->layoutRelative
            );
        }
        if (($layout['fsm_path'] ?? null) !== $this->fsmRelative) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_FSM_PATH_INVALID:'
                . $this->layoutRelative
            );
        }
        if (($layout['layout_direction'] ?? null) !== $this->layoutDirection) {
            /* Direction changed: keep state positions only when compatible by
             * explicit user persistence. Otherwise regenerate deterministically.
             */
            return null;
        }
        if (!is_array($layout['states'] ?? null)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_STATES_INVALID:'
                . $this->layoutRelative
            );
        }
        return $this->normalizeExistingPersisted($definition, $layout);
    }

    /** @return array<string,mixed> */
    private function readLayout(): array
    {
        $path = $this->siteRoot . '/' . $this->layoutRelative;
        $layout = $this->loader->read($path);
        if (!is_array($layout)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_INVALID:' . $this->layoutRelative
            );
        }
        return $layout;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $layout
     * @param array<string,mixed> $automatic
     * @return array<string,mixed>
     */
    private function normalizeExistingPersisted(
        array $definition,
        array $layout
    ): array {
        $known = $this->definitionStateSet($definition);
        $normalizedStates = [];
        foreach ((array) ($layout['states'] ?? []) as $id => $position) {
            if (!is_string($id)
                || !isset($known[$id])
                || !is_array($position)) {
                continue;
            }
            $normalizedStates[$id] = [
                'x' => $this->coordinate($position['x'] ?? null, 'x'),
                'y' => $this->coordinate($position['y'] ?? null, 'y'),
            ];
        }

        $layout['states'] = $normalizedStates;
        $layout['transitions'] = $this->normalizeTransitionGeometryMap(
            $definition,
            is_array($layout['transitions'] ?? null)
                ? $layout['transitions']
                : []
        );
        $layout['markers'] = $this->normalizeMarkerGeometryMap(
            $definition,
            is_array($layout['markers'] ?? null)
                ? $layout['markers']
                : []
        );
        return $layout;
    }

    /**
     * Merge the current FSM state set into an existing persisted layout.
     * Existing manual coordinates win; new states receive their deterministic
     * automatic coordinates; stale states have already been pruned.
     *
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $layout
     * @param array<string,mixed> $automatic
     * @return array<string,mixed>
     */
    private function mergeCurrentStates(
        array $definition,
        array $layout,
        array $automatic
    ): array {
        $layout = $this->normalizeExistingPersisted($definition, $layout);
        $states = (array) ($layout['states'] ?? []);
        foreach ((array) ($automatic['states'] ?? []) as $id => $position) {
            if (!is_string($id)
                || isset($states[$id])
                || !is_array($position)) {
                continue;
            }
            $states[$id] = [
                'x' => $this->coordinate($position['x'] ?? null, 'x'),
                'y' => $this->coordinate($position['y'] ?? null, 'y'),
            ];
        }

        $layout['contract'] = self::CONTRACT;
        $layout['fsm_path'] = $this->fsmRelative;
        $layout['definition_sha256'] = $automatic['definition_sha256'];
        $layout['layout_direction'] = $this->layoutDirection;
        $layout['canvas'] = is_array($layout['canvas'] ?? null)
            ? $layout['canvas']
            : $automatic['canvas'];
        $layout['states'] = $states;
        $layout['transitions'] = $this->normalizeTransitionGeometryMap(
            $definition,
            is_array($layout['transitions'] ?? null)
                ? $layout['transitions']
                : []
        );
        $layout['markers'] = $this->normalizeMarkerGeometryMap(
            $definition,
            is_array($layout['markers'] ?? null)
                ? $layout['markers']
                : []
        );
        return $layout;
    }

    private function matchesSaveRequest(): bool
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return false;
        }
        if (!in_array(
            (string) ($_POST['opus_fsm_layout_action'] ?? ''),
            [
                self::SAVE_STATE_ACTION,
                self::SAVE_SIGNAL_ACTION,
                self::SAVE_MARKER_ACTION,
            ],
            true
        )) {
            return false;
        }
        $key = trim((string) ($_POST['opus_fsm_layout_key'] ?? ''));
        return $key !== '' && hash_equals($this->layoutKey, $key);
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $layout
     * @return array<string,mixed>
     */
    private function applySaveRequest(array $definition, array $layout): array
    {
        $token = trim((string) ($_POST['csrf_token'] ?? ''));
        if (preg_match('/^[a-f0-9]{64}$/D', strtolower($token)) !== 1) {
            throw new RuntimeException('OPUS_CSRF_TOKEN_INVALID');
        }

        $action = (string) ($_POST['opus_fsm_layout_action'] ?? '');
        if ($action === self::SAVE_STATE_ACTION) {
            $stateId = trim((string) ($_POST['opus_fsm_layout_state'] ?? ''));
            $known = $this->definitionStateSet($definition);
            if ($stateId === '' || !isset($known[$stateId])) {
                throw new RuntimeException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_STATE_UNKNOWN:' . $stateId
                );
            }
            $x = $this->coordinate($_POST['opus_fsm_layout_x'] ?? null, 'x');
            $y = $this->coordinate($_POST['opus_fsm_layout_y'] ?? null, 'y');
            $layout['states'][$stateId] = ['x' => $x, 'y' => $y];
        } elseif ($action === self::SAVE_MARKER_ACTION) {
            $markerId = trim((string) ($_POST['opus_fsm_layout_marker'] ?? ''));
            $knownMarkers = $this->definitionMarkerSet($definition);
            if ($markerId === '' || !isset($knownMarkers[$markerId])) {
                throw new RuntimeException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_MARKER_UNKNOWN:' . $markerId
                );
            }
        } elseif ($action !== self::SAVE_SIGNAL_ACTION) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_ACTION_INVALID:' . $action
            );
        }

        $geometryRaw = (string) ($_POST['opus_fsm_layout_geometry'] ?? '');
        if ($geometryRaw !== '') {
            if (strlen($geometryRaw) > self::MAX_GEOMETRY_BYTES) {
                throw new RuntimeException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_GEOMETRY_TOO_LARGE'
                );
            }
            try {
                $geometry = json_decode(
                    $geometryRaw,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $error) {
                throw new RuntimeException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_GEOMETRY_JSON_INVALID',
                    0,
                    $error
                );
            }
            if (!is_array($geometry)) {
                throw new RuntimeException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_GEOMETRY_INVALID'
                );
            }
            $normalized = $this->normalizeGeometryPayload(
                $definition,
                $geometry
            );
            $layout['canvas'] = $normalized['canvas'];
            $layout['transitions'] = $normalized['transitions'];
            $layout['markers'] = $normalized['markers'];
        }

        (new CsrfTokenManager())->assertValid(
            $this->csrfScope(),
            $token
        );

        return $layout;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $payload
     * @return array{
     *   canvas:array{width:float,height:float},
     *   transitions:array<string,array{path:string,label_x:float,label_y:float,leader_path:string,path_kind:string,source_control:array{dx:float,dy:float},target_control:array{dx:float,dy:float}}>
     * }
     */
    private function normalizeGeometryPayload(
        array $definition,
        array $payload
    ): array {
        $canvas = is_array($payload['canvas'] ?? null)
            ? $payload['canvas']
            : [];
        $width = $this->positiveNumber($canvas['width'] ?? null, 'width');
        $height = $this->positiveNumber($canvas['height'] ?? null, 'height');
        $transitions = is_array($payload['transitions'] ?? null)
            ? $payload['transitions']
            : [];
        $markers = is_array($payload['markers'] ?? null)
            ? $payload['markers']
            : [];

        return [
            'canvas' => ['width' => $width, 'height' => $height],
            'transitions' => $this->normalizeTransitionGeometryMap(
                $definition,
                $transitions
            ),
            'markers' => $this->normalizeMarkerGeometryMap(
                $definition,
                $markers
            ),
        ];
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $geometryMap
     * @return array<string,array{path:string,label_x:float,label_y:float,leader_path:string,path_kind:string,source_control:array{dx:float,dy:float},target_control:array{dx:float,dy:float}}>
     */
    private function normalizeTransitionGeometryMap(
        array $definition,
        array $geometryMap
    ): array {
        $known = $this->definitionTransitionSet($definition);
        $normalized = [];
        foreach ($geometryMap as $id => $geometry) {
            if (!is_string($id)
                || !isset($known[$id])
                || !is_array($geometry)) {
                continue;
            }
            $pathKind = trim((string) ($geometry['path_kind'] ?? 'auto'));
            if (!in_array($pathKind, ['auto', 'cubic_bezier'], true)) {
                throw new RuntimeException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_PATH_KIND_INVALID:' . $id
                );
            }
            $sourceControl = is_array($geometry['source_control'] ?? null)
                ? $geometry['source_control']
                : [];
            $targetControl = is_array($geometry['target_control'] ?? null)
                ? $geometry['target_control']
                : [];
            if ($pathKind === 'cubic_bezier'
                && ($sourceControl === [] || $targetControl === [])) {
                throw new RuntimeException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_BEZIER_CONTROL_MISSING:' . $id
                );
            }
            $normalized[$id] = [
                'path' => $this->svgPath(
                    (string) ($geometry['path'] ?? ''),
                    'path'
                ),
                'label_x' => $this->coordinate(
                    $geometry['label_x'] ?? null,
                    'label_x'
                ),
                'label_y' => $this->coordinate(
                    $geometry['label_y'] ?? null,
                    'label_y'
                ),
                'leader_path' => $this->svgPath(
                    (string) ($geometry['leader_path'] ?? ''),
                    'leader_path'
                ),
                'path_kind' => $pathKind,
                'source_control' => [
                    'dx' => $this->signedCoordinate(
                        $sourceControl['dx'] ?? 0,
                        'source_control.dx'
                    ),
                    'dy' => $this->signedCoordinate(
                        $sourceControl['dy'] ?? 0,
                        'source_control.dy'
                    ),
                ],
                'target_control' => [
                    'dx' => $this->signedCoordinate(
                        $targetControl['dx'] ?? 0,
                        'target_control.dx'
                    ),
                    'dy' => $this->signedCoordinate(
                        $targetControl['dy'] ?? 0,
                        'target_control.dy'
                    ),
                ],
            ];
        }
        return $normalized;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $geometryMap
     * @return array<string,array{x:float,y:float}>
     */
    private function normalizeMarkerGeometryMap(
        array $definition,
        array $geometryMap
    ): array {
        $known = $this->definitionMarkerSet($definition);
        $normalized = [];
        foreach ($geometryMap as $id => $geometry) {
            if (!is_string($id)
                || !isset($known[$id])
                || !is_array($geometry)) {
                continue;
            }
            $normalized[$id] = [
                'x' => $this->coordinate($geometry['x'] ?? null, 'marker_x'),
                'y' => $this->coordinate($geometry['y'] ?? null, 'marker_y'),
            ];
        }
        return $normalized;
    }

    /** @param array<string,mixed> $definition @return array<string,true> */
    private function definitionMarkerSet(array $definition): array
    {
        $markers = [];
        $finiteSourceSets = [];
        foreach ((array) ($definition['transitions'] ?? []) as $transition) {
            if (!is_array($transition)
                || trim((string) ($transition['scope'] ?? '')) !== 'global') {
                continue;
            }
            $states = array_values(array_filter(
                (array) ($transition['from_states'] ?? []),
                'is_string'
            ));
            if ($states === []) {
                continue;
            }
            $key = implode("\0", $states);
            $finiteSourceSets[$key] = true;
        }
        foreach (array_keys($finiteSourceSets) as $key) {
            $markers['finite-global-source-'
                . substr(hash('sha256', $key), 0, 16)] = true;
        }

        $initial = trim((string) ($definition['initial_state'] ?? ''));
        if ($initial === '') {
            return $markers;
        }

        $states = $this->definitionStateSet($definition);
        if (isset($states[$initial])) {
            $markers['initial'] = true;
        }
        return $markers;
    }

    /**
     * @param array<string,mixed> $definition
     * @return array<string,string>
     */
    private function finiteGlobalMarkerIdsByTransition(
        array $definition
    ): array {
        $markers = [];
        $ordinal = 0;
        foreach ((array) ($definition['transitions'] ?? []) as $transition) {
            ++$ordinal;
            if (!is_array($transition)
                || trim((string) ($transition['scope'] ?? '')) !== 'global') {
                continue;
            }
            $transitionId = trim((string) (
                $transition['id'] ?? 'transition-' . $ordinal
            ));
            $states = array_values(array_filter(
                (array) ($transition['from_states'] ?? []),
                'is_string'
            ));
            if ($transitionId === '' || $states === []) {
                continue;
            }
            $markers[$transitionId] = 'finite-global-source-'
                . substr(hash('sha256', implode("\0", $states)), 0, 16);
        }
        return $markers;
    }

    /** @param array<string,mixed> $definition @return array<string,true> */
    private function definitionTransitionSet(array $definition): array
    {
        $transitions = [];
        $ordinal = 0;
        foreach ((array) ($definition['transitions'] ?? []) as $transition) {
            ++$ordinal;
            if (!is_array($transition)) {
                continue;
            }
            $id = trim((string) (
                $transition['id'] ?? 'transition-' . $ordinal
            ));
            if ($id !== '') {
                $transitions[$id] = true;
            }
        }
        return $transitions;
    }

    private function svgPath(string $value, string $name): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) > self::MAX_SVG_PATH_BYTES
            || preg_match(
                '/\\A[0-9eE.,+\\-\\sMmLlHhVvCcSsQqTtAaZz]+\\z/D',
                $value
            ) !== 1) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_SVG_PATH_INVALID:' . $name
            );
        }
        return $value;
    }

    /** @param array<string,mixed> $definition @return array<string,true> */
    private function definitionStateSet(array $definition): array
    {
        $states = [];
        foreach ((array) ($definition['states'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $id = trim((string) ($state['id'] ?? ''));
            if ($id !== '') {
                $states[$id] = true;
            }
        }
        if ($states === []) {
            throw new RuntimeException('OPUS_FSM_DIAGRAM_LAYOUT_STATES_EMPTY');
        }
        return $states;
    }

    private function write(array $layout): void
    {
        $this->file->writeAtomic(
            $this->siteRoot . '/' . $this->layoutRelative,
            $this->json->encode($layout, true)
        );
    }

    private function csrfScope(): string
    {
        return 'opus.fsm.layout.' . substr($this->layoutKey, 0, 24);
    }

    private function coordinate(mixed $value, string $axis): float
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_COORDINATE_INVALID:' . $axis
            );
        }
        if (!is_numeric($value)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_COORDINATE_INVALID:' . $axis
            );
        }
        $number = (float) $value;
        if (!is_finite($number)
            || $number < 0.0
            || $number > self::MAX_COORDINATE) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_COORDINATE_INVALID:' . $axis
            );
        }
        return round($number, 2);
    }

    private function signedCoordinate(mixed $value, string $axis): float
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_COORDINATE_INVALID:' . $axis
            );
        }
        if (!is_numeric($value)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_COORDINATE_INVALID:' . $axis
            );
        }
        $number = (float) $value;
        if (!is_finite($number)
            || abs($number) > self::MAX_COORDINATE) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_COORDINATE_INVALID:' . $axis
            );
        }
        return round($number, 2);
    }

    private function positiveNumber(mixed $value, string $name): float
    {
        if (!is_numeric($value)) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_CANVAS_INVALID:' . $name
            );
        }
        $number = (float) $value;
        if (!is_finite($number) || $number <= 0.0) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_LAYOUT_CANVAS_INVALID:' . $name
            );
        }
        return round($number, 2);
    }
}
