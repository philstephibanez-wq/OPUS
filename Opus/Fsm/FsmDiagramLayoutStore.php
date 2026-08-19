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
 * - persisted layout stores only presentation coordinates and canvas metadata;
 * - when no layout exists, OPUS persists the computed automatic layout in DEV;
 * - when a layout exists, persisted coordinates win;
 * - new FSM states are auto-positioned and merged without discarding existing
 *   manual coordinates;
 * - writes are allowed only under the PHP development server or when the
 *   explicit OPUS_FSM_LAYOUT_WRITE=1 override is present.
 */
final class FsmDiagramLayoutStore implements FsmDiagramLayoutStoreInterface
{
    public const CONTRACT = 'OPUS_FSM_DIAGRAM_LAYOUT_V1';
    private const SAVE_ACTION = 'save-state';
    private const MAX_COORDINATE = 100000.0;

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
        if (($layout['contract'] ?? null) !== self::CONTRACT) {
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
        $layout['canvas'] = $automatic['canvas'];
        $layout['states'] = $states;
        return $layout;
    }

    private function matchesSaveRequest(): bool
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return false;
        }
        if ((string) ($_POST['opus_fsm_layout_action'] ?? '') !== self::SAVE_ACTION) {
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
        (new CsrfTokenManager())->assertValid($this->csrfScope(), $token);

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
        return $layout;
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
