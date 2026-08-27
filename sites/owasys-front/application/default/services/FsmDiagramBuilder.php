<?php
declare(strict_types=1);

use Opus\File\File;
use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\Profiler\ProfilerInterface;

/** Builds a fixed visual projection from the canonical OWASYS FSM. */
final class OwasysFsmDiagramBuilder
{
    private const REVISION = 'P117W_R45B2A4BZ2R8B6P';

    private string $sourceHash = '';

    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysAuthSession $session,
        private readonly ?ProfilerInterface $profiler = null
    ) {
    }

    /**
     * The topology source is config/fsm.json. Presentation hints come only
     * from state.diagram {rank,order}; current state changes highlight only.
     *
     * Dense same-state technical workflows are reduced to one representative
     * self-loop per signal type, while every non-self workflow relation is
     * kept. Finite global transitions are rendered once as compact target-ingress
     * global-scope stacks above their target; their canonical from_states set remains in the
     * transition metadata instead of exploding into duplicate long rails.
     *
     * @param array<string,mixed> $pageData
     * @return array<string,mixed>
     */
    public function build(array $pageData): array
    {
        $identityView = is_array($pageData['identity'] ?? null)
            ? $pageData['identity']
            : [];
        $currentState = trim((string) ($pageData['fsm']['state'] ?? ''));

        if (($identityView['authenticated'] ?? false) !== true) {
            return $this->hidden();
        }
        if (!is_array($this->session->user())) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_IDENTITY_REQUIRED'
            );
        }

        $contextEfsmId = $this->contextEfsmId($pageData);
        if ($contextEfsmId !== '') {
            return $this->buildSelectedApplicationEfsm(
                $pageData,
                $contextEfsmId
            );
        }
        if (($pageData['fsm_designer']['active'] ?? false) === true) {
            throw new RuntimeException(
                'OWASYS_FSM_DESIGNER_CONTEXT_EFSM_REQUIRED'
            );
        }

        $fsm = $this->loadFsm();
        $statesById = $this->statesById($fsm);
        $signalRegistry = $this->signalRegistry($fsm);
        $this->assertSignalRegistryComplete($fsm, $signalRegistry);

        $menuByState = [];
        $stateLabels = [];
        foreach ((array) ($pageData['navigation'] ?? []) as $item) {
            if (!is_array($item) || ($item['allowed'] ?? false) !== true) {
                continue;
            }
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '' || !isset($statesById[$id])) {
                throw new RuntimeException(
                    'OWASYS_FSM_MENU_PROJECTION_INVALID:' . $id
                );
            }
            $menuByState[$id] = $item;
            /* Diagram is diagnostic: state IDs stay canonical, never I18n. */
            $stateLabels[$id] = $id;
        }

        if (!isset($statesById[$currentState], $menuByState[$currentState])) {
            return $this->hidden();
        }

        $initialState = trim((string) ($fsm['initial_state'] ?? ''));
        if ($initialState === '' || !isset($statesById[$initialState])) {
            throw new RuntimeException(
                'OWASYS_FSM_WORKFLOW_INITIAL_STATE_INVALID:' . $initialState
            );
        }

        $stateOrder = $this->orderedStates($statesById, $menuByState);
        if (($stateOrder[0] ?? '') !== $initialState) {
            throw new RuntimeException(
                'OWASYS_FSM_WORKFLOW_INITIAL_ORDER_DIVERGENCE:'
                . $initialState
            );
        }

        $states = [];
        $layout = [];
        foreach ($stateOrder as $stateId) {
            $states[] = $statesById[$stateId];
            $hint = is_array($statesById[$stateId]['diagram'] ?? null)
                ? $statesById[$stateId]['diagram']
                : [];
            $layout[$stateId] = [
                'rank' => (int) ($hint['rank'] ?? 0),
                'order' => (int) ($hint['order'] ?? 0),
            ];
        }

        $currentActions = $this->currentActions(
            $menuByState,
            $currentState
        );
        $transitions = [];
        $transitionLabels = [];
        $transitionLinks = [];
        $transitionActions = [];
        $displayedBySignalTarget = [];
        $selfLoopTypes = [];

        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                throw new RuntimeException(
                    'OWASYS_FSM_TRANSITION_ENTRY_INVALID'
                );
            }
            if (($transition['interrupt'] ?? null) === 'nmi') {
                continue;
            }

            $scope = trim((string) ($transition['scope'] ?? ''));
            $signal = trim((string) ($transition['signal'] ?? ''));
            $to = trim((string) ($transition['next_state'] ?? ''));
            $definition = $signalRegistry[$signal] ?? null;
            if (!is_array($definition) || !isset($menuByState[$to])) {
                continue;
            }

            if ($scope === 'global') {
                $sources = array_values(array_filter(
                    $this->globalSources($transition),
                    static fn (string $source): bool => isset($menuByState[$source])
                ));
                if ($sources === []) {
                    continue;
                }

                $clone = $transition;
                $clone['from'] = '@global';
                $clone['from_states'] = $sources;
                $this->appendTransition(
                    $clone,
                    $signal,
                    '@global',
                    $to,
                    $transitions,
                    $transitionLabels,
                    $displayedBySignalTarget
                );
                continue;
            }

            if ($scope !== '') {
                throw new RuntimeException(
                    'OWASYS_FSM_WORKFLOW_SCOPE_INVALID:' . $scope
                );
            }

            $from = trim((string) ($transition['from'] ?? ''));
            if (!isset($menuByState[$from])) {
                continue;
            }

            if ($from === $to) {
                $type = (string) ($definition['type'] ?? '');
                $loopKey = $from . "\0" . $type;
                if (isset($selfLoopTypes[$loopKey])) {
                    continue;
                }
                $selfLoopTypes[$loopKey] = true;
            }

            $this->appendTransition(
                $transition,
                $signal,
                $from,
                $to,
                $transitions,
                $transitionLabels,
                $displayedBySignalTarget
            );
        }

        foreach ($currentActions as $key => $action) {
            $candidates = $displayedBySignalTarget[$key] ?? [];
            if ($candidates === []) {
                continue;
            }
            $selected = $candidates[0];
            foreach ($candidates as $candidate) {
                if (($candidate['from'] ?? '') === $currentState) {
                    $selected = $candidate;
                    break;
                }
            }
            $id = (string) ($selected['transition_id'] ?? '');
            $url = (string) ($action['url'] ?? '');
            if ($id === '' || !$this->isLocalUrl($url)) {
                continue;
            }
            if (($action['is_post'] ?? false) === true) {
                $field = trim((string) ($action['request_field'] ?? ''));
                $value = (string) ($action['request_value'] ?? '');
                if ($field === '' || $value === '') {
                    throw new RuntimeException(
                        'OWASYS_FSM_CURRENT_POST_ACTION_INVALID:' . $id
                    );
                }
                $fields = [$field => $value];
                $csrf = trim((string) ($action['csrf_token'] ?? ''));
                if ($csrf !== '') {
                    $fields['csrf_token'] = $csrf;
                }
                $transitionActions[$id] = [
                    'method' => 'POST',
                    'url' => $url,
                    'fields' => $fields,
                ];
            } else {
                $transitionLinks[$id] = $url;
            }
        }

        if ($transitions === []) {
            throw new RuntimeException('OWASYS_FSM_WORKFLOW_EMPTY');
        }

        $definition = $fsm;
        $definition['name'] = 'OWASYS · FSM';
        $definition['states'] = $states;
        $definition['transitions'] = $transitions;
        $definition['initial_state'] = $initialState;
        unset($definition['final_state']);

        $diagram = \OPUS_FSM_Diagram::renderDefinition(
            $definition,
            $currentState,
            [],
            [],
            array_intersect_key(
                $stateLabels,
                array_fill_keys($stateOrder, true)
            ),
            $transitionLabels,
            false,
            $transitionLinks,
            $initialState,
            $layout,
            $transitionActions,
            'vertical'
        );

        $designerPayload = '';
        if (($pageData['fsm_designer']['active'] ?? false) === true) {
            $designerStates = [];
            foreach ($stateOrder as $stateId) {
                $designerStates[$stateId] = $statesById[$stateId];
            }

            $designerTransitions = [];
            $designerSignals = [];
            foreach ($transitions as $transition) {
                $transitionId = trim((string) ($transition['id'] ?? ''));
                $signalId = trim((string) ($transition['signal'] ?? ''));
                if ($transitionId === ''
                    || $signalId === ''
                    || !isset($signalRegistry[$signalId])) {
                    throw new RuntimeException(
                        'OWASYS_FSM_DESIGNER_SNAPSHOT_INVALID'
                    );
                }
                $designerTransitions[$transitionId] = $transition;
                $designerSignals[$signalId] = $signalRegistry[$signalId];
            }

            try {
                $encoded = json_encode(
                    [
                        'contract' => 'OWASYS_EFSM_DESIGNER_SNAPSHOT_V2',
                        'revision' => self::REVISION,
                        'base_sha256' => $this->sourceHash,
                        'current_state' => $currentState,
                        'initial_state' => $initialState,
                        'definition' => $fsm,
                        'states' => $designerStates,
                        'signals' => $designerSignals,
                        'transitions' => $designerTransitions,
                    ],
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                );
            } catch (JsonException $cause) {
                throw new RuntimeException(
                    'OWASYS_FSM_DESIGNER_SNAPSHOT_ENCODING_FAILED',
                    0,
                    $cause
                );
            }
            $designerPayload = base64_encode($encoded);
        }

        return [
            'visible' => true,
            'description' => 'EFSM OWASYS hôte non encore découpée · départ '
                . $stateLabels[$initialState]
                . ' · état courant '
                . $stateLabels[$currentState]
                . ' surligné uniquement · globals finis',
            'html' => $diagram,
            'application_id' => 'owasys-front',
            'efsm_id' => 'navigation',
            'source_path' => 'config/fsm.json',
            'source_sha256' => $this->sourceHash,
            'current_state' => $currentState,
            'current_label' => $stateLabels[$currentState],
            'projected_transition_count' => count($transitions),
            'designer_payload' => $designerPayload,
            'revision' => self::REVISION,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $statesById
     * @param array<string,array<string,mixed>> $menuByState
     * @return list<string>
     */
    private function orderedStates(array $statesById, array $menuByState): array
    {
        $ordered = [];
        foreach ($statesById as $id => $state) {
            if (!isset($menuByState[$id])) {
                continue;
            }
            $hint = is_array($state['diagram'] ?? null)
                ? $state['diagram']
                : [];
            $ordered[] = [
                'id' => $id,
                'rank' => (int) ($hint['rank'] ?? PHP_INT_MAX),
                'order' => (int) ($hint['order'] ?? PHP_INT_MAX),
            ];
        }
        usort(
            $ordered,
            static fn (array $left, array $right): int =>
                ($left['rank'] <=> $right['rank'])
                ?: ($left['order'] <=> $right['order'])
                ?: strcmp((string) $left['id'], (string) $right['id'])
        );
        return array_column($ordered, 'id');
    }

    /**
     * @param array<string,mixed> $transition
     * @param list<array<string,mixed>> $transitions
     * @param array<string,string> $labels
     * @param array<string,list<array{transition_id:string,from:string}>> $displayed
     */
    private function appendTransition(
        array $transition,
        string $signal,
        string $from,
        string $to,
        array &$transitions,
        array &$labels,
        array &$displayed
    ): void {
        $id = trim((string) ($transition['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException(
                'OWASYS_FSM_WORKFLOW_TRANSITION_ID_MISSING:'
                . $from . ':' . $signal . ':' . $to
            );
        }
        $transitions[] = $transition;
        $key = $this->signalTargetKey($signal, $to);
        $displayed[$key] ??= [];
        $displayed[$key][] = [
            'transition_id' => $id,
            'from' => $from,
        ];
    }


    /**
     * @param array<string,array<string,mixed>> $menuByState
     * @return array<string,array{url:string,transition_id:string,is_post:bool,request_field:string,request_value:string,csrf_token:string}>
     */
    private function currentActions(
        array $menuByState,
        string $currentState
    ): array {
        $actions = [];
        $current = $menuByState[$currentState] ?? [];

        foreach ((array) ($current['signals'] ?? []) as $signalItem) {
            if (!is_array($signalItem)
                || ($signalItem['menu_actionable'] ?? false) !== true) {
                continue;
            }
            $this->registerAction($actions, $signalItem);
        }

        foreach ($menuByState as $item) {
            if (!is_array($item)
                || ($item['global_host'] ?? false) !== true) {
                continue;
            }
            foreach ((array) ($item['global_signals'] ?? []) as $signalItem) {
                if (!is_array($signalItem)
                    || ($signalItem['menu_actionable'] ?? false) !== true) {
                    continue;
                }
                $this->registerAction($actions, $signalItem);
            }
        }

        return $actions;
    }

    /**
     * @param array<string,array{url:string,transition_id:string,is_post:bool,request_field:string,request_value:string,csrf_token:string}> $actions
     * @param array<string,mixed> $signalItem
     */
    private function registerAction(array &$actions, array $signalItem): void
    {
        $signal = trim((string) ($signalItem['signal'] ?? ''));
        $target = trim((string) ($signalItem['target'] ?? ''));
        $url = trim((string) ($signalItem['url'] ?? ''));
        $transitionId = trim((string) (
            $signalItem['transition_id'] ?? ''
        ));
        if ($signal === '' || $target === '' || $transitionId === '') {
            throw new RuntimeException(
                'OWASYS_FSM_CURRENT_ACTION_INVALID:' . $transitionId
            );
        }
        $key = $this->signalTargetKey($signal, $target);
        $actions[$key] = [
            'url' => $url,
            'transition_id' => $transitionId,
            'is_post' => ($signalItem['is_post'] ?? false) === true,
            'request_field' => trim((string) (
                $signalItem['request_field'] ?? ''
            )),
            'request_value' => (string) (
                $signalItem['request_value'] ?? ''
            ),
            'csrf_token' => trim((string) (
                $signalItem['csrf_token'] ?? ''
            )),
        ];
    }

    private function signalTargetKey(string $signal, string $target): string
    {
        return $signal . "\0" . $target;
    }

    /** @param array<string,mixed> $transition @return list<string> */
    private function globalSources(array $transition): array
    {
        $sources = $transition['from_states'] ?? null;
        if (!is_array($sources) || $sources === []) {
            throw new RuntimeException(
                'OWASYS_FSM_GLOBAL_SOURCES_MISSING:'
                . (string) ($transition['signal'] ?? '')
            );
        }
        $result = [];
        foreach ($sources as $source) {
            if (!is_string($source) || trim($source) === '') {
                throw new RuntimeException(
                    'OWASYS_FSM_GLOBAL_SOURCE_INVALID'
                );
            }
            $result[] = trim($source);
        }
        return array_values(array_unique($result));
    }

    /**
     * @param array<string,mixed> $fsm
     * @return array<string,array<string,mixed>>
     */
    private function signalRegistry(array $fsm): array
    {
        $registry = [];
        foreach ((array) ($fsm['signals'] ?? []) as $signal) {
            if (!is_array($signal)) {
                throw new RuntimeException(
                    'OWASYS_FSM_SIGNAL_REGISTRY_ENTRY_INVALID'
                );
            }
            $id = trim((string) ($signal['id'] ?? ''));
            $origin = trim((string) ($signal['origin'] ?? ''));
            if ($id === '' || isset($registry[$id])) {
                throw new RuntimeException(
                    'OWASYS_FSM_SIGNAL_REGISTRY_ID_INVALID:' . $id
                );
            }
            if (!in_array($origin, ['user', 'automatic'], true)) {
                throw new RuntimeException(
                    'OWASYS_FSM_SIGNAL_ORIGIN_INVALID:'
                    . $id . ':' . $origin
                );
            }
            $registry[$id] = $signal;
        }
        return $registry;
    }

    /**
     * @param array<string,mixed> $fsm
     * @param array<string,array<string,mixed>> $registry
     */
    private function assertSignalRegistryComplete(
        array $fsm,
        array $registry
    ): void {
        $referenced = [];
        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                throw new RuntimeException(
                    'OWASYS_FSM_TRANSITION_ENTRY_INVALID'
                );
            }
            $signal = trim((string) ($transition['signal'] ?? ''));
            if ($signal === '' || !isset($registry[$signal])) {
                throw new RuntimeException(
                    'OWASYS_FSM_SIGNAL_UNDECLARED:' . $signal
                );
            }
            $referenced[$signal] = true;
        }
        $unused = array_diff_key($registry, $referenced);
        if ($unused !== []) {
            throw new RuntimeException(
                'OWASYS_FSM_SIGNAL_UNUSED:'
                . implode(',', array_keys($unused))
            );
        }
    }

    /**
     * @param array<string,mixed> $fsm
     * @return array<string,array<string,mixed>>
     */
    private function statesById(array $fsm): array
    {
        $states = [];
        foreach ((array) ($fsm['states'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $id = trim((string) ($state['id'] ?? ''));
            if ($id !== '') {
                $states[$id] = $state;
            }
        }
        return $states;
    }

    private function isLocalUrl(string $url): bool
    {
        return $url !== ''
            && $url[0] === '/'
            && !str_contains($url, "\0");
    }

    /** @return array<string,mixed> */
    private function hidden(): array
    {
        return [
            'visible' => false,
            'description' => '',
            'html' => '',
            'application_id' => '',
            'efsm_id' => '',
            'source_path' => '',
            'source_sha256' => '',
            'current_state' => '',
            'current_label' => '',
            'projected_transition_count' => 0,
            'designer_payload' => '',
            'revision' => self::REVISION,
        ];
    }

    /**
     * Development mode is an application tool: its semantic source is the
     * currently selected application's canonical EFSM, read through secured
     * OWASYS REST. The host navigation FSM is never substituted.
     *
     * @param array<string,mixed> $pageData
     * @return array<string,mixed>
     */
    private function buildSelectedApplicationEfsm(array $pageData, string $efsmId): array
    {
        $contextRegistry = new OwasysContextEfsmRegistry();
        $hostContext = $contextRegistry->isHostEfsm($efsmId);
        $applicationId = $hostContext
            ? 'owasys-front'
            : strtolower(trim((string) (
                $pageData['current_app']['id'] ?? ''
            )));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $applicationId) !== 1) {
            throw new RuntimeException(
                'OWASYS_FSM_DESIGNER_APPLICATION_REQUIRED'
            );
        }

        $identity = $this->session->user();
        if (!is_array($identity)) {
            throw new RuntimeException(
                'OWASYS_FSM_DESIGNER_IDENTITY_REQUIRED'
            );
        }

        $snapshot = (new OwasysApplicationFsmModel(
            new OwasysSourceModel($this->siteRoot)
        ))->snapshot($applicationId, $identity, $efsmId);
        $definition = is_array($snapshot['definition'] ?? null)
            ? $snapshot['definition']
            : [];
        $initialState = trim((string) (
            $definition['initial_state'] ?? ''
        ));
        $sourcePath = trim((string) (
            $snapshot['source_path'] ?? ''
        ));
        $sourceHash = strtolower(trim((string) (
            $snapshot['sha256'] ?? ''
        )));
        if ($definition === []
            || $initialState === ''
            || $sourcePath === ''
            || preg_match('/^[a-f0-9]{64}$/D', $sourceHash) !== 1) {
            throw new RuntimeException(
                'OWASYS_FSM_DESIGNER_APPLICATION_SNAPSHOT_INVALID'
            );
        }

        $currentState = $initialState;
        $runtimeMemory = [];
        $runtimeSessionKey = '';
        if ($hostContext) {
            $runtimeSessionKey = $contextRegistry->sessionKey($efsmId);
        } elseif ($applicationId === 'owasys-front') {
            $runtimeSessionKey = match ($efsmId) {
                'navigation' => OwasysNavigationRuntime::SESSION_KEY,
                'security' => 'opus.fsm.owasys-front.security',
                default => '',
            };
        }
        if ($runtimeSessionKey !== '') {
            $runtime = FsmSiteLoader::processorForSiteRootEfsm(
                $this->siteRoot,
                $efsmId,
                [],
                $this->profiler
            );
            (new FsmSessionStore($runtimeSessionKey))->restore($runtime);
            $currentState = $runtime->currentState();
            $runtimeMemory = $runtime->memory();
        }

        $layoutSnapshot = (new OwasysApplicationFsmLayoutModel(
            $this->siteRoot,
            null,
            $this->profiler
        ))->snapshot($applicationId, $efsmId, $identity);
        $layout = is_array($layoutSnapshot['layout'] ?? null)
            ? $layoutSnapshot['layout']
            : [];
        $layoutPath = trim((string) (
            $layoutSnapshot['layout_path'] ?? ''
        ));
        if ((string) ($layoutSnapshot['source_path'] ?? '') !== $sourcePath
            || (string) ($layoutSnapshot['source_sha256'] ?? '') !== $sourceHash
            || $layoutPath === ''
            || $layout === []) {
            throw new RuntimeException(
                'OWASYS_FSM_DESIGNER_LAYOUT_SNAPSHOT_DIVERGENCE'
            );
        }

        $layoutDirection = strtolower(trim((string) (
            $layout['layout_direction'] ?? ''
        )));
        if (!in_array($layoutDirection, ['horizontal', 'vertical'], true)) {
            throw new RuntimeException(
                'OWASYS_FSM_DESIGNER_LAYOUT_DIRECTION_INVALID'
            );
        }
        $contextualDiagram = \OPUS_FSM_Diagram::fromDefinition(
            $definition,
            $currentState,
            $runtimeMemory
        );
        $contextualDiagram->setLayoutDirection($layoutDirection);
        $contextualDiagram->setPersistedStatePositions(
            is_array($layout['states'] ?? null) ? $layout['states'] : []
        );
        $contextualDiagram->setPersistedCanvas(
            is_array($layout['canvas'] ?? null) ? $layout['canvas'] : []
        );
        $contextualDiagram->setPersistedTransitionGeometry(
            is_array($layout['transitions'] ?? null)
                ? $layout['transitions']
                : []
        );
        $contextualDiagram->setPersistedMarkerGeometry(
            is_array($layout['markers'] ?? null) ? $layout['markers'] : []
        );

        if (($pageData['fsm_designer']['active'] ?? false) === true) {
            $csrfToken = strtolower(trim((string) (
                $pageData['fsm_designer']['csrf_token'] ?? ''
            )));
            if (preg_match('/^[a-f0-9]{64}$/D', $csrfToken) !== 1) {
                throw new RuntimeException(
                    'OWASYS_FSM_DESIGNER_LAYOUT_CSRF_INVALID'
                );
            }
            $contextualDiagram->setLayoutPersistence([
                'writable' => true,
                'layout_key' => hash(
                    'sha256',
                    'owasys.contextual.layout.v1' . "\0"
                        . $applicationId . "\0"
                        . $efsmId . "\0"
                        . $sourceHash
                ),
                'csrf_token' => $csrfToken,
                'layout_path' => $layoutPath,
                'efsm_id' => $efsmId,
                'definition_sha256' => $sourceHash,
            ]);
        }
        $contextualDiagramHtml = $contextualDiagram->renderHtml();

        try {
            $encoded = json_encode(
                [
                    'contract' => 'OWASYS_EFSM_DESIGNER_SNAPSHOT_V4',
                    'efsm_id' => $efsmId,
                    'revision' => self::REVISION,
                    'application_id' => $applicationId,
                    'source_path' => $sourcePath,
                    'base_sha256' => $sourceHash,
                    'current_state' => $currentState,
                    'initial_state' => $initialState,
                    'handler_authoring_supported' =>
                        $applicationId === 'owasys-front',
                    'definition' => $definition,
                ],
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $cause) {
            throw new RuntimeException(
                'OWASYS_FSM_DESIGNER_APPLICATION_SNAPSHOT_ENCODING_FAILED',
                0,
                $cause
            );
        }

        return [
            'visible' => true,
            'description' => 'Application '
                . $applicationId
                . ' · micro-EFSM '
                . $efsmId
                . ' · source canonique '
                . $sourcePath,
            'html' => $contextualDiagramHtml,
            'application_id' => $applicationId,
            'efsm_id' => $efsmId,
            'source_path' => $sourcePath,
            'source_sha256' => $sourceHash,
            'current_state' => $currentState,
            'current_label' => $currentState,
            'projected_transition_count' => (int) (
                $snapshot['transition_count'] ?? 0
            ),
            'designer_payload' => ($pageData['fsm_designer']['active'] ?? false)
                ? base64_encode($encoded)
                : '',
            'revision' => self::REVISION,
        ];
    }
    /** @param array<string,mixed> $pageData */
    private function contextEfsmId(array $pageData): string
    {
        return (new OwasysContextEfsmRegistry())->efsmIdForPage($pageData);
    }
    /** @return array<string,mixed> */
    private function loadFsm(): array
    {
        $loader = StructuredFileLoader::instance();
        try {
            $site = $loader->read($this->siteRoot . '/config/site.json');
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_SITE_CONFIG_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
            );
        }

        $navigation = is_array($site['navigation'] ?? null)
            ? $site['navigation']
            : [];
        $relative = trim(
            str_replace(
                '\\',
                '/',
                (string) ($navigation['fsm'] ?? '')
            ),
            '/'
        );
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_CONFIG_PATH_INVALID'
            );
        }

        $fsmPath = $this->siteRoot . '/' . $relative;
        try {
            $raw = File::instance()->read($fsmPath, 2097152);
            $this->sourceHash = hash('sha256', $raw);
            $fsm = $loader->read($fsmPath);
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_CONFIG_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
            );
        }
        if (($fsm['contract'] ?? null) !== 'OWASYS_NAVIGATION_FSM_V1') {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_CONTRACT_INVALID'
            );
        }
        return $fsm;
    }
}
