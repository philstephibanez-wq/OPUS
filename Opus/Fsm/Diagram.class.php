<?php
declare(strict_types=1);

/**
 * Semantic SVG renderer for OPUS finite-state machines.
 *
 * The renderer is intentionally dependency-free:
 * - no GraphViz;
 * - no external process execution;
 * - no external JavaScript dependency; development-only layout dragging is
 *   emitted inline only when portable layout persistence is writable;
 * - one visual edge per real transition.
 *
 * Transition labels follow the state-machine convention:
 *     signal [guard] / effect
 */
final class OPUS_FSM_Diagram implements OPUS_FSM_DiagramInterface
{
    private string $_title;
    private string $_initialState;
    private string $_finalState;
    private string $_currentState;

    /** @var array<string,mixed> */
    private array $_memory;

    /** @var array<string,string> */
    private array $_states = [];

    /**
     * @var list<array{
     *   id:string,
     *   from:string,
     *   to:string,
     *   signal:string,
     *   guards:list<string>,
     *   actions:list<string>,
     *   runtime_operations:list<array<string,mixed>>,
     *   scope:string,
     *   from_states:list<string>,
     *   wildcard:bool,
     *   fallback:bool
     * }>
     */
    private array $_transitions = [];

    /**
     * Legacy state annotations kept only for compatibility with callers that
     * used addAction() independently of a transition.
     *
     * @var array<string,list<string>>
     */
    private array $_stateAnnotations = [];

    /** @var array<string,string> */
    private array $_stateLinks = [];

    /** @var array<string,string> */
    private array $_stateLabels = [];

    /** @var array<string,string> */
    private array $_transitionLabels = [];

    /** @var array<string,string> */
    private array $_transitionLinks = [];

    /**
     * @var array<string,array{method:string,url:string,fields:array<string,string>}>
     */
    private array $_transitionActions = [];

    private bool $_compactLayout = false;
    private string $_layoutDirection = 'horizontal';
    private string $_layoutRoot = '';

    /** @var array<string,array{rank:int,order:int}> */
    private array $_stateLayoutHints = [];

    /** @var list<string> */
    private array $_fallbackEffects = [];

    /**
     * Current render geometry, used only to keep transition labels readable.
     *
     * @var array<string,array{x:float,y:float,w:float,h:float,rank:int}>
     */
    private array $_renderPositions = [];

    /**
     * Reserved transition-label rectangles for deterministic collision
     * avoidance inside one SVG render.
     *
     * @var list<array{x1:float,y1:float,x2:float,y2:float}>
     */
    private array $_renderedLabelBoxes = [];
    private float $_renderWidth = 0.0;
    private float $_renderHeight = 0.0;

    /** @var array<string,array{x:float,y:float}> */
    private array $_persistedStatePositions = [];

    /**
     * Persisted presentation geometry for transitions. Local edge paths are
     * topology-validated; signal-card coordinates may be persisted for local,
     * global and self transitions. EFSM semantics are never stored here.
     *
     * @var array<string,array{path:string,label_x:float,label_y:float,leader_path:string}>
     */
    private array $_persistedTransitionGeometry = [];

    /**
     * Geometry actually emitted by the latest SVG render.
     *
     * @var array<string,array{path:string,label_x:float,label_y:float,leader_path:string}>
     */
    private array $_renderedTransitionGeometry = [];

    /**
     * Persisted presentation geometry for non-semantic diagram markers.
     *
     * @var array<string,array{x:float,y:float}>
     */
    private array $_persistedMarkerGeometry = [];

    /**
     * Marker geometry actually emitted by the latest SVG render.
     *
     * @var array<string,array{x:float,y:float}>
     */
    private array $_renderedMarkerGeometry = [];

    /** @var array<string,mixed> */
    private array $_layoutPersistence = [];

    public function __construct(
        string $title = 'OPUS FSM',
        string $initialState = '',
        string $finalState = '',
        string $currentState = '',
        array $memory = []
    ) {
        $this->_title = $title !== '' ? $title : 'OPUS FSM';
        $this->_initialState = $initialState;
        $this->_finalState = $finalState;
        $this->_currentState = $currentState;
        $this->_memory = $memory;

        foreach ([$initialState, $finalState, $currentState] as $state) {
            if ($state !== '') {
                $this->addState($state);
            }
        }
    }

    public static function renderRuntime(
        string $title,
        string $initialState,
        string $finalState,
        string $currentState,
        array $memory,
        array $transitions
    ): string {
        return self::fromTransitions(
            $title,
            $initialState,
            $finalState,
            $currentState,
            $memory,
            $transitions
        )->renderHtml();
    }

    /**
     * Accepts both legacy Transition objects and canonical transition arrays.
     */
    public static function fromTransitions(
        string $title,
        string $initialState,
        string $finalState,
        string $currentState,
        array $memory,
        array $transitions
    ): self {
        $diagram = new self(
            $title,
            $initialState,
            $finalState,
            $currentState,
            $memory
        );

        $ordinal = 0;
        foreach ($transitions as $transition) {
            ++$ordinal;

            if (is_object($transition)) {
                $signal = isset($transition->signal)
                    ? (string) $transition->signal
                    : '';
                $from = isset($transition->state)
                    ? (string) $transition->state
                    : '';
                $to = isset($transition->nextState)
                    ? (string) $transition->nextState
                    : '';
                $action = isset($transition->action)
                    ? trim((string) $transition->action)
                    : '';

                if ($signal === '__default__' && ($from === '' || $to === '')) {
                    if ($action !== '') {
                        $diagram->_fallbackEffects[] = $action;
                    }
                    continue;
                }

                if ($from === '' || $to === '' || $signal === '') {
                    continue;
                }

                $diagram->addTransition(
                    'legacy-' . $ordinal,
                    $from,
                    $to,
                    $signal,
                    [],
                    $action === '' ? [] : [$action],
                    []
                );
                continue;
            }

            if (!is_array($transition)) {
                continue;
            }

            $signal = trim((string) ($transition['signal'] ?? ''));
            $from = trim((string) (
                $transition['from']
                ?? $transition['state']
                ?? ''
            ));
            $to = trim((string) (
                $transition['next_state']
                ?? $transition['nextState']
                ?? ''
            ));
            $interrupt = trim((string) ($transition['interrupt'] ?? ''));
            $signalType = self::signalType(
                (string) ($transition['signal_type'] ?? 'system')
            );
            $signalOrigin = self::signalOrigin(
                (string) ($transition['signal_origin'] ?? '')
            );
            $scope = trim((string) ($transition['scope'] ?? ''));
            $fromStates = self::stringList($transition['from_states'] ?? []);

            if ($scope !== '' && $scope !== 'global') {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_SCOPE_INVALID:' . $scope
                );
            }
            if ($interrupt !== '' && $interrupt !== 'nmi') {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_INTERRUPT_INVALID:' . $interrupt
                );
            }
            if ($from === '*' && $interrupt !== 'nmi') {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_GLOBAL_SOURCE_FORBIDDEN:' . $signal
                );
            }
            if ($interrupt === 'nmi' && $from !== '*') {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_NMI_SOURCE_INVALID:' . $from
                );
            }

            if ($signal === '__default__' && ($from === '' || $to === '')) {
                foreach (self::stringList(
                    $transition['actions']
                    ?? $transition['action']
                    ?? []
                ) as $effect) {
                    $diagram->_fallbackEffects[] = $effect;
                }
                continue;
            }

            if ($scope === 'global') {
                if ($to === '' || $signal === '' || $fromStates === []) {
                    continue;
                }
                $from = '@global';
            } elseif ($from === '' || $to === '' || $signal === '') {
                continue;
            }

            $diagram->addTransition(
                trim((string) ($transition['id'] ?? 'transition-' . $ordinal)),
                $from,
                $to,
                $signal,
                self::stringList(
                    $transition['guards']
                    ?? $transition['guard']
                    ?? []
                ),
                self::stringList(
                    $transition['actions']
                    ?? $transition['action']
                    ?? []
                ),
                is_array($transition['runtime_operations'] ?? null)
                    ? array_values(array_filter(
                        $transition['runtime_operations'],
                        'is_array'
                    ))
                    : []
                ,
                $signalType,
                $signalOrigin,
                $scope,
                $fromStates
            );
        }

        return $diagram;
    }

    /**
     * Builds directly from a canonical FsmProcessor-compatible definition.
     *
     * @param array<string,mixed> $fsm
     */
    public static function fromDefinition(
        array $fsm,
        string $currentState = '',
        array $memory = []
    ): self {
        $title = trim((string) ($fsm['name'] ?? 'OPUS FSM'));
        $initial = trim((string) ($fsm['initial_state'] ?? ''));
        $final = trim((string) ($fsm['final_state'] ?? ''));

        $states = is_array($fsm['states'] ?? null)
            ? $fsm['states']
            : [];
        if ($final === '') {
            foreach ($states as $state) {
                if (!is_array($state)) {
                    continue;
                }
                if (($state['final'] ?? false) === true
                    || ($state['terminal'] ?? false) === true) {
                    $candidate = trim((string) ($state['id'] ?? ''));
                    if ($candidate !== '') {
                        $final = $candidate;
                        break;
                    }
                }
            }
        }

        $diagram = new self(
            $title,
            $initial,
            $final,
            $currentState,
            $memory
        );

        foreach ($states as $state) {
            if (!is_array($state)) {
                continue;
            }
            $id = trim((string) ($state['id'] ?? ''));
            if ($id !== '') {
                $diagram->addState($id);
            }
        }

        $transitions = is_array($fsm['transitions'] ?? null)
            ? $fsm['transitions']
            : [];

        $signalMetadata = [];
        foreach ((array) ($fsm['signals'] ?? []) as $signalDefinition) {
            if (!is_array($signalDefinition)) {
                continue;
            }
            $signalId = trim((string) ($signalDefinition['id'] ?? ''));
            if ($signalId === '') {
                continue;
            }
            $signalMetadata[$signalId] = [
                'type' => self::signalType(
                    (string) ($signalDefinition['type'] ?? 'system')
                ),
                'origin' => self::signalOrigin(
                    (string) ($signalDefinition['origin'] ?? '')
                ),
            ];
        }
        foreach ($transitions as $index => $transition) {
            if (!is_array($transition)) {
                continue;
            }
            $signalId = trim((string) ($transition['signal'] ?? ''));
            if ($signalId !== '' && isset($signalMetadata[$signalId])) {
                $transitions[$index]['signal_type'] =
                    $signalMetadata[$signalId]['type'];
                $transitions[$index]['signal_origin'] =
                    $signalMetadata[$signalId]['origin'];
            }
        }

        $built = self::fromTransitions(
            $title,
            $initial,
            $final,
            $currentState,
            $memory,
            $transitions
        );

        $orderedStates = [];
        foreach ($states as $state) {
            if (!is_array($state)) {
                continue;
            }
            $stateId = trim((string) ($state['id'] ?? ''));
            if ($stateId !== '') {
                $orderedStates[$stateId] = $stateId;
            }
        }
        foreach (array_keys($built->_states) as $state) {
            if (!isset($orderedStates[$state])) {
                $orderedStates[$state] = $state;
            }
        }
        $built->_states = $orderedStates;

        return $built;
    }

    /**
     * Convenience entry point for canonical FsmProcessor definitions.
     *
     * @param array<string,mixed> $fsm
     */
    public static function renderDefinition(
        array $fsm,
        string $currentState = '',
        array $memory = [],
        array $stateLinks = [],
        array $stateLabels = [],
        array $transitionLabels = [],
        bool $compactLayout = false,
        array $transitionLinks = [],
        string $layoutRoot = '',
        array $stateLayoutHints = [],
        array $transitionActions = [],
        string $layoutDirection = 'horizontal'
    ): string {
        $diagram = self::fromDefinition(
            $fsm,
            $currentState,
            $memory
        );
        $diagram->setStateLinks($stateLinks);
        $diagram->setStateLabels($stateLabels);
        $diagram->setTransitionLabels($transitionLabels);
        $diagram->setCompactLayout($compactLayout);
        $diagram->setLayoutDirection($layoutDirection);
        $diagram->setTransitionLinks($transitionLinks);
        $diagram->setTransitionActions($transitionActions);
        $diagram->setLayoutRoot($layoutRoot);
        $diagram->setStateLayoutHints($stateLayoutHints);

        $layoutStore = null;
        if (class_exists(\Opus\Fsm\FsmDiagramLayoutStore::class)) {
            $candidateStore = \Opus\Fsm\FsmDiagramLayoutStore::discover(
                $fsm,
                $layoutDirection
            );
            if ($candidateStore instanceof \Opus\Fsm\FsmDiagramLayoutStoreInterface) {
                $layoutStore = $candidateStore;
                $automaticLayout = $diagram->layoutSnapshot();
                $persistedLayout = $layoutStore->resolve(
                    $fsm,
                    $automaticLayout
                );
                $diagram->setPersistedStatePositions(
                    is_array($persistedLayout['states'] ?? null)
                        ? $persistedLayout['states']
                        : []
                );
                $diagram->setPersistedTransitionGeometry(
                    is_array($persistedLayout['transitions'] ?? null)
                        ? $persistedLayout['transitions']
                        : []
                );
                $diagram->setPersistedMarkerGeometry(
                    is_array($persistedLayout['markers'] ?? null)
                        ? $persistedLayout['markers']
                        : []
                );
                $diagram->setLayoutPersistence(
                    $layoutStore->clientConfig()
                );
            }
        }

        $html = $diagram->renderHtml();
        if ($layoutStore instanceof \Opus\Fsm\FsmDiagramLayoutStoreInterface) {
            $layoutStore->persistRenderedGeometry(
                $fsm,
                $diagram->renderedLayoutSnapshot()
            );
        }
        return $html;
    }

    public static function renderDemoHtml(): string
    {
        $fsm = [
            'contract' => 'OPUS_APPLICATION_FSM_V1',
            'name' => 'OPUS demo FSM',
            'initial_state' => 'IDLE',
            'final_state' => 'DONE',
            'states' => [
                ['id' => 'IDLE'],
                ['id' => 'BOOTSTRAP'],
                ['id' => 'ROUTE_FOUND'],
                ['id' => 'VIEW_READY'],
                ['id' => 'DONE', 'final' => true],
            ],
            'transitions' => [
                [
                    'id' => 'boot',
                    'from' => 'IDLE',
                    'signal' => 'HTTP_REQUEST',
                    'next_state' => 'BOOTSTRAP',
                    'actions' => ['loadConfig'],
                ],
                [
                    'id' => 'route',
                    'from' => 'BOOTSTRAP',
                    'signal' => 'ROUTE_MATCH',
                    'next_state' => 'ROUTE_FOUND',
                    'actions' => ['dispatchController'],
                ],
                [
                    'id' => 'view',
                    'from' => 'ROUTE_FOUND',
                    'signal' => 'ACTION_OK',
                    'next_state' => 'VIEW_READY',
                    'actions' => ['drawTemplate'],
                ],
                [
                    'id' => 'render',
                    'from' => 'VIEW_READY',
                    'signal' => 'RENDER',
                    'next_state' => 'DONE',
                ],
            ],
        ];

        return self::renderDefinition(
            $fsm,
            'ROUTE_FOUND',
            [
                'url' => '/fr/démo-interne',
                'page' => 'default',
                'language' => 'fr-FR',
            ]
        );
    }

    public function addState(string $name): void
    {
        $name = trim($name);
        if ($name === '' || $name === '*') {
            return;
        }
        $this->_states[$name] = $name;
    }

    /** @param array<string,string> $stateLinks */
    public function setStateLinks(array $stateLinks): void
    {
        $normalized = [];
        foreach ($stateLinks as $state => $href) {
            if (!is_string($state)
                || !is_string($href)
                || !isset($this->_states[$state])) {
                continue;
            }
            $href = trim($href);
            if ($href === ''
                || $href[0] !== '/'
                || str_contains($href, "\0")) {
                continue;
            }
            $normalized[$state] = $href;
        }
        $this->_stateLinks = $normalized;
    }

    /** @param array<string,string> $stateLabels */
    public function setStateLabels(array $stateLabels): void
    {
        $normalized = [];
        foreach ($stateLabels as $state => $label) {
            if (!is_string($state)
                || !is_string($label)
                || !isset($this->_states[$state])) {
                continue;
            }

            $label = trim($label);
            if ($label !== '') {
                $normalized[$state] = $label;
            }
        }
        $this->_stateLabels = $normalized;
    }

    /** @param array<string,string> $transitionLabels */
    public function setTransitionLabels(array $transitionLabels): void
    {
        $known = [];
        foreach ($this->_transitions as $transition) {
            $known[$transition['id']] = true;
        }

        $normalized = [];
        foreach ($transitionLabels as $transitionId => $label) {
            if (!is_string($transitionId)
                || !is_string($label)
                || !isset($known[$transitionId])) {
                continue;
            }

            $label = trim($label);
            if ($label !== '') {
                $normalized[$transitionId] = $label;
            }
        }
        $this->_transitionLabels = $normalized;
    }

    public function setCompactLayout(bool $compactLayout): void
    {
        $this->_compactLayout = $compactLayout;
    }

    public function setLayoutDirection(string $layoutDirection): void
    {
        $layoutDirection = strtolower(trim($layoutDirection));
        if (!in_array($layoutDirection, ['horizontal', 'vertical'], true)) {
            throw new \InvalidArgumentException(
                'OPUS_FSM_DIAGRAM_LAYOUT_DIRECTION_INVALID:'
                . $layoutDirection
            );
        }
        $this->_layoutDirection = $layoutDirection;
    }


    /**
     * Persisted coordinates are presentation-only and never alter FSM
     * semantics or state ordering metadata.
     *
     * @param array<string,array{x:mixed,y:mixed}> $positions
     */
    public function setPersistedStatePositions(array $positions): void
    {
        $normalized = [];
        foreach ($positions as $state => $position) {
            if (!is_string($state)
                || !isset($this->_states[$state])
                || !is_array($position)
                || !is_numeric($position['x'] ?? null)
                || !is_numeric($position['y'] ?? null)) {
                continue;
            }
            $x = (float) $position['x'];
            $y = (float) $position['y'];
            if (!is_finite($x) || !is_finite($y) || $x < 0.0 || $y < 0.0) {
                continue;
            }
            $normalized[$state] = ['x' => $x, 'y' => $y];
        }
        $this->_persistedStatePositions = $normalized;
    }

    /**
     * @param array<string,array{path:mixed,label_x:mixed,label_y:mixed,leader_path:mixed}> $geometry
     */
    public function setPersistedTransitionGeometry(array $geometry): void
    {
        $known = [];
        foreach ($this->_transitions as $transition) {
            $known[(string) $transition['id']] = $transition;
        }

        $normalized = [];
        foreach ($geometry as $id => $item) {
            if (!is_string($id)
                || !isset($known[$id])
                || !is_array($item)) {
                continue;
            }
            if (!is_numeric($item['label_x'] ?? null)
                || !is_numeric($item['label_y'] ?? null)) {
                continue;
            }
            $normalized[$id] = [
                'path' => is_string($item['path'] ?? null)
                    ? $item['path']
                    : '',
                'label_x' => (float) $item['label_x'],
                'label_y' => (float) $item['label_y'],
                'leader_path' => is_string($item['leader_path'] ?? null)
                    ? $item['leader_path']
                    : '',
            ];
        }
        $this->_persistedTransitionGeometry = $normalized;
    }

    /**
     * @param array<string,array{x:mixed,y:mixed}> $geometry
     */
    public function setPersistedMarkerGeometry(array $geometry): void
    {
        $normalized = [];
        $initial = $geometry['initial'] ?? null;
        if (is_array($initial)
            && is_numeric($initial['x'] ?? null)
            && is_numeric($initial['y'] ?? null)) {
            $x = (float) $initial['x'];
            $y = (float) $initial['y'];
            if (is_finite($x) && is_finite($y) && $x >= 0.0 && $y >= 0.0) {
                $normalized['initial'] = ['x' => $x, 'y' => $y];
            }
        }
        $this->_persistedMarkerGeometry = $normalized;
    }

    /**
     * Geometry emitted by the last render. The store persists presentation
     * only; FSM semantics are never duplicated in the layout companion file.
     *
     * @return array<string,mixed>
     */
    public function renderedLayoutSnapshot(): array
    {
        $states = [];
        foreach ($this->_renderPositions as $state => $position) {
            $states[$state] = [
                'x' => (float) $position['x'],
                'y' => (float) $position['y'],
            ];
        }

        return [
            'canvas' => [
                'width' => max(1.0, $this->_renderWidth),
                'height' => max(1.0, $this->_renderHeight),
            ],
            'states' => $states,
            'transitions' => $this->_renderedTransitionGeometry,
            'markers' => $this->_renderedMarkerGeometry,
        ];
    }

    /** @param array<string,mixed> $config */
    public function setLayoutPersistence(array $config): void
    {
        $this->_layoutPersistence = $config;
    }

    /**
     * Return deterministic geometry before persisted coordinates are applied.
     * This is used to bootstrap a new portable *.fsm.layout.json file.
     *
     * @return array{
     *   positions:array<string,array{x:float,y:float,w:float,h:float,rank:int}>,
     *   width:float,
     *   height:float
     * }
     */
    public function layoutSnapshot(): array
    {
        $persisted = $this->_persistedStatePositions;
        $this->_persistedStatePositions = [];
        try {
            $states = array_values($this->_states);
            if ($states === []) {
                $states = ['EMPTY'];
            }
            return $this->layout($states);
        } finally {
            $this->_persistedStatePositions = $persisted;
        }
    }

    public function setLayoutRoot(string $layoutRoot): void
    {
        $layoutRoot = trim($layoutRoot);
        if ($layoutRoot === '') {
            $this->_layoutRoot = '';
            return;
        }
        if (!isset($this->_states[$layoutRoot])) {
            throw new \InvalidArgumentException(
                'OPUS_FSM_DIAGRAM_LAYOUT_ROOT_UNKNOWN:' . $layoutRoot
            );
        }
        $this->_layoutRoot = $layoutRoot;
    }

    /**
     * Optional generic presentation hints for a fixed diagram projection.
     * They never modify FSM semantics; they only choose visual rank/order.
     *
     * @param array<string,array{rank:int,order?:int}> $hints
     */
    public function setStateLayoutHints(array $hints): void
    {
        $normalized = [];
        foreach ($hints as $state => $hint) {
            if (!is_string($state)
                || !isset($this->_states[$state])
                || !is_array($hint)) {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_HINT_INVALID:' . (string) $state
                );
            }
            $rank = $hint['rank'] ?? null;
            $order = $hint['order'] ?? 0;
            if (!is_int($rank) || $rank < 0 || !is_int($order)) {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_LAYOUT_HINT_VALUE_INVALID:' . $state
                );
            }
            $normalized[$state] = [
                'rank' => $rank,
                'order' => $order,
            ];
        }
        $this->_stateLayoutHints = $normalized;
    }

    /** @param array<string,string> $transitionLinks */
    public function setTransitionLinks(array $transitionLinks): void
    {
        $known = [];
        foreach ($this->_transitions as $transition) {
            $known[$transition['id']] = true;
        }

        $normalized = [];
        foreach ($transitionLinks as $transitionId => $href) {
            if (!is_string($transitionId)
                || !is_string($href)
                || !isset($known[$transitionId])) {
                continue;
            }

            $href = trim($href);
            if ($href === ''
                || $href[0] !== '/'
                || str_contains($href, "\0")) {
                continue;
            }
            $normalized[$transitionId] = $href;
        }

        $this->_transitionLinks = $normalized;
    }

    /**
     * Structured non-GET transition actions. HTTP transport is intentionally
     * orthogonal to FSM signal metadata and never controls signal color.
     *
     * @param array<string,array{method:string,url:string,fields?:array<string,string>}> $transitionActions
     */
    public function setTransitionActions(array $transitionActions): void
    {
        $known = [];
        foreach ($this->_transitions as $transition) {
            $known[$transition['id']] = true;
        }

        $normalized = [];
        foreach ($transitionActions as $transitionId => $action) {
            if (!is_string($transitionId)
                || !isset($known[$transitionId])
                || !is_array($action)) {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_TRANSITION_ACTION_INVALID:'
                    . (string) $transitionId
                );
            }
            if (isset($this->_transitionLinks[$transitionId])) {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_TRANSITION_ACTION_AMBIGUOUS:'
                    . $transitionId
                );
            }

            $method = strtoupper(trim((string) ($action['method'] ?? '')));
            $url = trim((string) ($action['url'] ?? ''));
            if ($method !== 'POST') {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_TRANSITION_ACTION_METHOD_INVALID:'
                    . $transitionId . ':' . $method
                );
            }
            if ($url === ''
                || $url[0] !== '/'
                || str_contains($url, "\0")
                || str_contains($url, "\r")
                || str_contains($url, "\n")) {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_TRANSITION_ACTION_URL_INVALID:'
                    . $transitionId
                );
            }

            $fields = $action['fields'] ?? [];
            if (!is_array($fields)) {
                throw new \InvalidArgumentException(
                    'OPUS_FSM_DIAGRAM_TRANSITION_ACTION_FIELDS_INVALID:'
                    . $transitionId
                );
            }
            $normalizedFields = [];
            foreach ($fields as $name => $value) {
                if (!is_string($name)
                    || !is_string($value)
                    || preg_match(
                        '/^[A-Za-z_][A-Za-z0-9_.-]{0,127}$/D',
                        $name
                    ) !== 1
                    || str_contains($value, "\0")) {
                    throw new \InvalidArgumentException(
                        'OPUS_FSM_DIAGRAM_TRANSITION_ACTION_FIELD_INVALID:'
                        . $transitionId
                    );
                }
                $normalizedFields[$name] = $value;
            }

            $normalized[$transitionId] = [
                'method' => 'POST',
                'url' => $url,
                'fields' => $normalizedFields,
            ];
        }

        $this->_transitionActions = $normalized;
    }

    /**
     * Backward-compatible edge API. Each call remains one distinct transition.
     */
    public function addEdge(
        string $from,
        string $to,
        string $label = ''
    ): void {
        $this->addTransition(
            'edge-' . (count($this->_transitions) + 1),
            $from,
            $to,
            $label !== '' ? $label : 'transition',
            [],
            [],
            []
        );
    }

    /**
     * Legacy compatibility. Standalone actions are rendered as state
     * annotations because their transition identity is unknowable here.
     */
    public function addAction(
        string $state,
        string $action,
        bool $isDefault = false
    ): void {
        $action = trim($action);
        if ($action === '') {
            return;
        }

        if ($isDefault) {
            $this->_fallbackEffects[] = $action;
            return;
        }

        $state = trim($state);
        if ($state === '') {
            return;
        }

        $this->addState($state);
        $this->_stateAnnotations[$state] ??= [];
        if (!in_array(
            $action,
            $this->_stateAnnotations[$state],
            true
        )) {
            $this->_stateAnnotations[$state][] = $action;
        }
    }

    /**
     * @param list<string> $guards
     * @param list<string> $actions
     * @param list<array<string,mixed>> $runtimeOperations
     */
    private function addTransition(
        string $id,
        string $from,
        string $to,
        string $signal,
        array $guards,
        array $actions,
        array $runtimeOperations,
        string $signalType = 'system',
        string $signalOrigin = 'unspecified',
        string $scope = '',
        array $fromStates = []
    ): void {
        $from = trim($from);
        $to = trim($to);
        $signal = trim($signal);
        $signalType = self::signalType($signalType);
        $signalOrigin = self::signalOrigin($signalOrigin);
        $scope = trim($scope);
        if ($scope !== '' && $scope !== 'global') {
            throw new \InvalidArgumentException(
                'OPUS_FSM_DIAGRAM_SCOPE_INVALID:' . $scope
            );
        }
        if ($from === '' || $to === '' || $signal === '') {
            return;
        }

        if ($from !== '*' && $from !== '@global') {
            $this->addState($from);
        }
        $this->addState($to);

        $this->_transitions[] = [
            'id' => $id !== ''
                ? $id
                : 'transition-' . (count($this->_transitions) + 1),
            'from' => $from,
            'to' => $to,
            'signal' => $signal,
            'signal_type' => $signalType,
            'signal_origin' => $signalOrigin,
            'guards' => array_values($guards),
            'actions' => array_values($actions),
            'runtime_operations' => array_values($runtimeOperations),
            'scope' => $scope,
            'from_states' => array_values($fromStates),
            'wildcard' => $from === '*'
                || $signal === '__any__',
            'fallback' => $signal === '__default__',
        ];
    }

    public function renderHtml(): string
    {
        $runtime = $this->renderRuntimeFacts();
        $writable = ($this->_layoutPersistence['writable'] ?? false) === true;
        $attributes = '';
        if ($this->_layoutPersistence !== []) {
            $attributes .= ' data-opus-fsm-layout-key="'
                . self::h((string) (
                    $this->_layoutPersistence['layout_key'] ?? ''
                )) . '"';
            $attributes .= ' data-opus-fsm-layout-csrf="'
                . self::h((string) (
                    $this->_layoutPersistence['csrf_token'] ?? ''
                )) . '"';
            $attributes .= ' data-opus-fsm-layout-path="'
                . self::h((string) (
                    $this->_layoutPersistence['layout_path'] ?? ''
                )) . '"';
            $attributes .= ' data-opus-fsm-layout-writable="'
                . ($writable ? '1' : '0') . '"';
        }

        $html = '<div class="fsm-diagram-card"' . $attributes . '>'
            . '<div class="fsm-diagram-toolbar">'
            . '<strong>' . self::h($this->_title) . '</strong>'
            . '<span>FSM · SVG natif · transitions sémantiques</span>'
            . '</div>'
            . $this->renderSvg()
            . $runtime
            . '</div>';

        return $html . ($writable ? self::layoutInteractionScript() : '');
    }

    public function renderSvg(): string
    {
        $states = array_values($this->_states);
        if ($states === []) {
            $states = ['EMPTY'];
        }

        $layout = $this->layout($states);
        $positions = $layout['positions'];
        $width = $layout['width'];
        $height = $layout['height'];

        $this->_renderPositions = $positions;
        $this->_renderedLabelBoxes = [];
        $this->_renderedTransitionGeometry = [];
        $this->_renderedMarkerGeometry = [];
        if ($this->_layoutDirection === 'vertical') {
            $this->reserveVerticalFixedTransitionBoxes($positions);
        }
        $this->_renderWidth = $width;
        $this->_renderHeight = $height;

        $svg = '<svg class="fsm-diagram"'
            . ' data-opus-fsm-routing="lane-aware-v5-signal-origin"'
            . ' data-opus-fsm-layout="' . self::h($this->_layoutDirection) . '"'
            . ' width="' . self::n($width)
            . '" height="' . self::n($height)
            . '" viewBox="0 0 '
            . self::n($width)
            . ' '
            . self::n($height)
            . '" preserveAspectRatio="xMinYMin meet"'
            . ' role="img" aria-labelledby="fsm-title fsm-desc">';

        $svg .= '<title id="fsm-title">'
            . self::h($this->_title)
            . '</title>';
        $svg .= '<desc id="fsm-desc">'
            . 'États et transitions orientées. '
            . 'Chaque transition conserve son signal, ses gardes et ses effets.'
            . '</desc>';

        $svg .= self::svgDefinitions();
        $svg .= '<text x="34" y="34" class="fsm-title">'
            . self::h($this->_title)
            . '</text>';
        $svg .= '<text x="34" y="57" class="fsm-subtitle">'
            . 'signal [garde] / effet'
            . '</text>';

        if ($this->hasGlobalSource()) {
            $svg .= $this->renderGlobalSource($positions);
        }

        $pairOrdinals = [];
        $pairTotals = [];
        $sourceTotals = [];
        $targetTotals = [];
        $sourceOrdinalById = [];
        $targetOrdinalById = [];
        $sourceGroups = [];
        $targetGroups = [];

        foreach ($this->_transitions as $transition) {
            $key = $transition['from'] . "\0" . $transition['to'];
            $pairTotals[$key] = ($pairTotals[$key] ?? 0) + 1;

            if ($transition['from'] !== '*'
                && $transition['from'] !== $transition['to']
                && isset(
                    $positions[$transition['from']],
                    $positions[$transition['to']]
                )) {
                $source = $transition['from'];
                $target = $transition['to'];
                $sourceGroups[$source] ??= [];
                $targetGroups[$target] ??= [];
                $sourceGroups[$source][] = $transition;
                $targetGroups[$target][] = $transition;
            }
        }

        /*
         * Geometry-aware port ordering. Declaration order is semantic, not a
         * drawing hint. Sorting each source by target Y and each target by
         * source Y minimizes crossings around high-degree states.
         */
        foreach ($sourceGroups as $source => $group) {
            usort(
                $group,
                fn (array $left, array $right): int =>
                    $this->transitionCounterpartOrder(
                        $left['to'],
                        $right['to'],
                        $positions
                    )
                    ?: strcmp($left['id'], $right['id'])
            );
            $sourceTotals[$source] = count($group);
            foreach ($group as $ordinal => $transition) {
                $sourceOrdinalById[$transition['id']] = $ordinal;
            }
        }

        foreach ($targetGroups as $target => $group) {
            usort(
                $group,
                fn (array $left, array $right): int =>
                    $this->transitionCounterpartOrder(
                        $left['from'],
                        $right['from'],
                        $positions
                    )
                    ?: strcmp($left['id'], $right['id'])
            );
            $targetTotals[$target] = count($group);
            foreach ($group as $ordinal => $transition) {
                $targetOrdinalById[$transition['id']] = $ordinal;
            }
        }

        foreach ($this->_transitions as $transition) {
            $key = $transition['from'] . "\0" . $transition['to'];
            $pairOrdinals[$key] = ($pairOrdinals[$key] ?? 0) + 1;

            $source = $transition['from'];
            $target = $transition['to'];
            $sourceOrdinal = $sourceOrdinalById[$transition['id']] ?? 0;
            $sourceTotal = $sourceTotals[$source] ?? 1;
            $targetOrdinal = $targetOrdinalById[$transition['id']] ?? 0;
            $targetTotal = $targetTotals[$target] ?? 1;

            $svg .= $this->renderTransition(
                $transition,
                $positions,
                $pairOrdinals[$key] - 1,
                $pairTotals[$key],
                $sourceOrdinal,
                $sourceTotal,
                $targetOrdinal,
                $targetTotal
            );
        }

        $svg .= $this->renderInitialMarker($positions);

        foreach ($positions as $state => $position) {
            $svg .= $this->renderState($state, $position);
        }

        $svg .= $this->renderFinalMarker($positions);
        $svg .= $this->renderLegend($width, $height);
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * @param array<string,array{x:float,y:float,w:float,h:float,rank:int}> $positions
     */
    private function transitionCounterpartOrder(
        string $leftState,
        string $rightState,
        array $positions
    ): int {
        $left = $positions[$leftState] ?? null;
        $right = $positions[$rightState] ?? null;
        if (!is_array($left) || !is_array($right)) {
            return strcmp($leftState, $rightState);
        }

        $leftY = $left['y'] + $left['h'] / 2;
        $rightY = $right['y'] + $right['h'] / 2;
        $leftX = $left['x'] + $left['w'] / 2;
        $rightX = $right['x'] + $right['w'] / 2;

        if ($this->_layoutDirection === 'vertical') {
            if (abs($leftX - $rightX) >= 0.5) {
                return $leftX <=> $rightX;
            }
            if (abs($leftY - $rightY) >= 0.5) {
                return $leftY <=> $rightY;
            }
        } else {
            if (abs($leftY - $rightY) >= 0.5) {
                return $leftY <=> $rightY;
            }
            if (abs($leftX - $rightX) >= 0.5) {
                return $leftX <=> $rightX;
            }
        }

        return strcmp($leftState, $rightState);
    }

    /**
     * @param list<string> $states
     * @return array{
     *   positions:array<string,array{x:float,y:float,w:float,h:float,rank:int}>,
     *   width:float,
     *   height:float
     * }
     */
    private function layout(array $states): array
    {
        if ($this->_layoutDirection === 'vertical') {
            return $this->applyPersistedStatePositions(
                $this->layoutVertical($states)
            );
        }

        $nodeW = $this->_compactLayout ? 176.0 : 204.0;
        $nodeH = $this->_compactLayout ? 68.0 : 76.0;
        $rankGap = $this->_compactLayout ? 38.0 : 220.0;
        $rowGap = $this->_compactLayout ? 48.0 : 112.0;
        $marginX = $this->_compactLayout ? 48.0 : 130.0;
        $marginY = $this->hasGlobalSource()
            ? ($this->_compactLayout ? 126.0 : 214.0)
            : ($this->_compactLayout ? 76.0 : 168.0);

        $layoutRoot = $this->_layoutRoot !== ''
            ? $this->_layoutRoot
            : $this->_initialState;

        if ($this->_compactLayout
            && $layoutRoot !== ''
            && isset($this->_states[$layoutRoot])) {
            $stateSet = array_fill_keys($states, true);
            $directTargets = [];
            $directTransitionCount = 0;

            foreach ($this->_transitions as $transition) {
                if ($transition['from'] !== $layoutRoot
                    || $transition['to'] === $layoutRoot
                    || !isset($stateSet[$transition['to']])) {
                    continue;
                }

                $directTargets[$transition['to']] = true;
                ++$directTransitionCount;
            }

            $onlyRootAndDirectTargets = count($directTargets) >= 4;
            if ($onlyRootAndDirectTargets) {
                foreach ($states as $state) {
                    if ($state !== $layoutRoot
                        && !isset($directTargets[$state])) {
                        $onlyRootAndDirectTargets = false;
                        break;
                    }
                }
            }

            if ($onlyRootAndDirectTargets) {
                $targets = [];
                foreach ($states as $state) {
                    if ($state !== $layoutRoot
                        && isset($directTargets[$state])) {
                        $targets[] = $state;
                    }
                }

                $targetCount = count($targets);
                $rows = min(
                    3,
                    max(
                        2,
                        (int) ceil(sqrt((float) $targetCount))
                    )
                );
                $columns = (int) ceil($targetCount / $rows);
                $fanRankGap = 180.0;
                $fanRowGap = 64.0;
                $signalLaneGap = 28.0;

                $gridHeight = $rows * $nodeH
                    + max(0, $rows - 1) * $fanRowGap;
                $signalLaneSpan = max(
                    $gridHeight,
                    max(0, $directTransitionCount - 1)
                        * $signalLaneGap
                        + 100.0
                );
                $targetTop = $marginY
                    + max(0.0, ($signalLaneSpan - $gridHeight) / 2);

                $positions = [
                    $layoutRoot => [
                        'x' => $marginX,
                        'y' => $marginY
                            + max(
                                0.0,
                                ($signalLaneSpan - $nodeH) / 2
                            ),
                        'w' => $nodeW,
                        'h' => $nodeH,
                        'rank' => 0,
                    ],
                ];

                foreach ($targets as $index => $state) {
                    $column = intdiv($index, $rows);
                    $row = $index % $rows;

                    $positions[$state] = [
                        'x' => $marginX
                            + $nodeW
                            + $fanRankGap
                            + $column * ($nodeW + $fanRankGap),
                        'y' => $targetTop
                            + $row * ($nodeH + $fanRowGap),
                        'w' => $nodeW,
                        'h' => $nodeH,
                        'rank' => $column + 1,
                    ];
                }

                $width = max(
                    760.0,
                    $marginX * 2
                        + ($columns + 1) * $nodeW
                        + $columns * $fanRankGap
                );
                $height = max(
                    390.0,
                    $marginY + $signalLaneSpan + 92.0
                );

                return [
                    'positions' => $positions,
                    'width' => $width,
                    'height' => $height,
                ];
            }
        }

        $ranks = [];
        if ($layoutRoot !== ''
            && isset($this->_states[$layoutRoot])) {
            $ranks[$layoutRoot] = 0;
            $queue = [$layoutRoot];

            while ($queue !== []) {
                $from = array_shift($queue);
                $fromRank = $ranks[$from];

                foreach ($this->_transitions as $transition) {
                    if ($transition['from'] !== $from
                        || $transition['to'] === $from
                        || !isset($this->_states[$transition['to']])) {
                        continue;
                    }

                    $to = $transition['to'];
                    if (!isset($ranks[$to])) {
                        $ranks[$to] = $fromRank + 1;
                        $queue[] = $to;
                    }
                }
            }
        }

        $maxReachable = $ranks === [] ? -1 : max($ranks);
        foreach ($states as $state) {
            if (!isset($ranks[$state])) {
                ++$maxReachable;
                $ranks[$state] = $maxReachable;
            }
        }

        foreach ($this->_stateLayoutHints as $state => $hint) {
            if (isset($ranks[$state])) {
                $ranks[$state] = $hint['rank'];
            }
        }

        $byRank = [];
        foreach ($states as $state) {
            $rank = $ranks[$state];
            $byRank[$rank] ??= [];
            $byRank[$rank][] = $state;
        }
        ksort($byRank, SORT_NUMERIC);
        foreach ($byRank as &$rankStates) {
            usort(
                $rankStates,
                fn (string $left, string $right): int =>
                    ($this->_stateLayoutHints[$left]['order'] ?? 0)
                    <=> ($this->_stateLayoutHints[$right]['order'] ?? 0)
                    ?: array_search($left, $states, true)
                    <=> array_search($right, $states, true)
            );
        }
        unset($rankStates);

        $maxRows = 1;
        foreach ($byRank as $rankStates) {
            $maxRows = max($maxRows, count($rankStates));
        }

        $positions = [];
        foreach ($byRank as $rank => $rankStates) {
            $columnHeight = count($rankStates) * $nodeH
                + max(0, count($rankStates) - 1) * $rowGap;
            $availableHeight = $maxRows * $nodeH
                + max(0, $maxRows - 1) * $rowGap;
            $offsetY = ($availableHeight - $columnHeight) / 2;

            foreach (array_values($rankStates) as $row => $state) {
                $positions[$state] = [
                    'x' => $marginX
                        + $rank * ($nodeW + $rankGap),
                    'y' => $marginY
                        + $offsetY
                        + $row * ($nodeH + $rowGap),
                    'w' => $nodeW,
                    'h' => $nodeH,
                    'rank' => (int) $rank,
                ];
            }
        }

        $rankCount = max(1, count($byRank));
        $width = max(
            $this->_compactLayout ? 680.0 : 760.0,
            $marginX * 2
                + $rankCount * $nodeW
                + max(0, $rankCount - 1) * $rankGap
                + ($this->hasGlobalSource()
                    ? ($this->_compactLayout ? 36.0 : 70.0)
                    : 0.0)
        );
        $height = max(
            $this->_compactLayout ? 310.0 : 430.0,
            $marginY
                + $maxRows * $nodeH
                + max(0, $maxRows - 1) * $rowGap
                + ($this->_compactLayout ? 92.0 : 260.0)
        );

        return $this->applyPersistedStatePositions([
            'positions' => $positions,
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Top-to-bottom ranked layout. Rank is vertical depth; states sharing a
     * rank are distributed horizontally. The SVG keeps one bounded page-width
     * viewBox while height grows with the workflow depth.
     *
     * @param list<string> $states
     * @return array{
     *   positions:array<string,array{x:float,y:float,w:float,h:float,rank:int}>,
     *   width:float,
     *   height:float
     * }
     */
    /**
     * Apply portable persisted x/y coordinates after deterministic layout has
     * computed semantic rank/order and node dimensions. Persisted geometry wins
     * for known states; missing states retain automatic coordinates.
     *
     * @param array{
     *   positions:array<string,array{x:float,y:float,w:float,h:float,rank:int}>,
     *   width:float,
     *   height:float
     * } $layout
     * @return array{
     *   positions:array<string,array{x:float,y:float,w:float,h:float,rank:int}>,
     *   width:float,
     *   height:float
     * }
     */
    private function applyPersistedStatePositions(array $layout): array
    {
        if ($this->_persistedStatePositions === []) {
            return $layout;
        }

        $positions = $layout['positions'];
        $maxRight = 0.0;
        $maxBottom = 0.0;
        foreach ($positions as $state => &$position) {
            $persisted = $this->_persistedStatePositions[$state] ?? null;
            if (is_array($persisted)) {
                $position['x'] = (float) $persisted['x'];
                $position['y'] = (float) $persisted['y'];
            }
            $maxRight = max($maxRight, $position['x'] + $position['w']);
            $maxBottom = max($maxBottom, $position['y'] + $position['h']);
        }
        unset($position);

        $layout['positions'] = $positions;
        $layout['width'] = max((float) $layout['width'], $maxRight + 48.0);
        $layout['height'] = max((float) $layout['height'], $maxBottom + 72.0);
        return $layout;
    }

    private function layoutVertical(array $states): array
    {
        $nodeW = $this->_compactLayout ? 160.0 : 180.0;
        $nodeH = $this->_compactLayout ? 60.0 : 66.0;
        $columnGap = $this->_compactLayout ? 24.0 : 30.0;
        $globalNodeGap = $this->_compactLayout ? 12.0 : 14.0;
        $rankGap = $this->_compactLayout ? 82.0 : 96.0;
        $marginX = $this->_compactLayout ? 28.0 : 38.0;
        $marginY = $this->hasGlobalSource()
            ? ($this->_compactLayout ? 104.0 : 118.0)
            : ($this->_compactLayout ? 68.0 : 78.0);

        $layoutRoot = $this->_layoutRoot !== ''
            ? $this->_layoutRoot
            : $this->_initialState;

        $ranks = [];
        if ($layoutRoot !== '' && isset($this->_states[$layoutRoot])) {
            $ranks[$layoutRoot] = 0;
            $queue = [$layoutRoot];
            while ($queue !== []) {
                $from = array_shift($queue);
                $fromRank = $ranks[$from];
                foreach ($this->_transitions as $transition) {
                    if (($transition['scope'] ?? '') === 'global'
                        || $transition['from'] === '@global'
                        || $transition['from'] !== $from
                        || $transition['to'] === $from
                        || !isset($this->_states[$transition['to']])) {
                        continue;
                    }
                    $to = $transition['to'];
                    if (!isset($ranks[$to])) {
                        $ranks[$to] = $fromRank + 1;
                        $queue[] = $to;
                    }
                }
            }
        }

        $maxReachable = $ranks === [] ? -1 : max($ranks);
        foreach ($states as $state) {
            if (!isset($ranks[$state])) {
                ++$maxReachable;
                $ranks[$state] = $maxReachable;
            }
        }
        foreach ($this->_stateLayoutHints as $state => $hint) {
            if (isset($ranks[$state])) {
                $ranks[$state] = $hint['rank'];
            }
        }

        $byRank = [];
        foreach ($states as $state) {
            $rank = $ranks[$state];
            $byRank[$rank] ??= [];
            $byRank[$rank][] = $state;
        }
        ksort($byRank, SORT_NUMERIC);
        foreach ($byRank as &$rankStates) {
            usort(
                $rankStates,
                fn (string $left, string $right): int =>
                    ($this->_stateLayoutHints[$left]['order'] ?? 0)
                    <=> ($this->_stateLayoutHints[$right]['order'] ?? 0)
                    ?: array_search($left, $states, true)
                    <=> array_search($right, $states, true)
            );
        }
        unset($rankStates);

        /*
         * Global transitions are ingress semantics, not side-car columns.
         * Their cards are stacked immediately above their target. This keeps
         * one target cell narrow and uses vertical space only where needed.
         */
        $globalMetrics = $this->verticalGlobalMetricsByTarget();
        $selfLoopMetrics = $this->verticalSelfLoopMetricsByState();
        $rowWidths = [];
        $rowIngressHeights = [];
        $rowSelfLoopHeights = [];
        $cellWidthsByRank = [];
        foreach ($byRank as $rank => $rankStates) {
            $rowWidth = 0.0;
            $rowIngressHeight = 0.0;
            $rowSelfLoopHeight = 0.0;
            $cellWidths = [];
            foreach (array_values($rankStates) as $index => $state) {
                $targetMetrics = $globalMetrics[$state] ?? null;
                $selfMetrics = $selfLoopMetrics[$state] ?? null;
                $cellW = max(
                    $nodeW,
                    is_array($targetMetrics)
                        ? (float) $targetMetrics['max_width']
                        : 0.0,
                    is_array($selfMetrics)
                        ? (float) $selfMetrics['max_width']
                        : 0.0
                );
                $cellWidths[$state] = $cellW;
                $rowIngressHeight = max(
                    $rowIngressHeight,
                    is_array($targetMetrics)
                        ? (float) $targetMetrics['stack_height']
                        : 0.0
                );
                $rowSelfLoopHeight = max(
                    $rowSelfLoopHeight,
                    is_array($selfMetrics)
                        ? (float) $selfMetrics['stack_height']
                        : 0.0
                );
                $rowWidth += $cellW;
                if ($index > 0) {
                    $rowWidth += $columnGap;
                }
            }
            $rowWidths[$rank] = $rowWidth;
            $rowIngressHeights[$rank] = $rowIngressHeight;
            $rowSelfLoopHeights[$rank] = $rowSelfLoopHeight;
            $cellWidthsByRank[$rank] = $cellWidths;
        }

        $contentWidth = $rowWidths === [] ? $nodeW : max($rowWidths);
        $width = max(
            $this->_compactLayout ? 360.0 : 420.0,
            $marginX * 2 + $contentWidth
        );

        $positions = [];
        $y = $marginY;
        foreach ($byRank as $rank => $rankStates) {
            $rowWidth = $rowWidths[$rank] ?? $nodeW;
            $rowIngressHeight = $rowIngressHeights[$rank] ?? 0.0;
            $rowSelfLoopHeight = $rowSelfLoopHeights[$rank] ?? 0.0;
            $x = ($width - $rowWidth) / 2;
            $stateY = $y
                + $rowIngressHeight
                + ($rowIngressHeight > 0.0 ? $globalNodeGap : 0.0);

            foreach (array_values($rankStates) as $state) {
                $cellW = $cellWidthsByRank[$rank][$state] ?? $nodeW;
                $positions[$state] = [
                    'x' => $x + ($cellW - $nodeW) / 2,
                    'y' => $stateY,
                    'w' => $nodeW,
                    'h' => $nodeH,
                    'rank' => (int) $rank,
                ];
                $x += $cellW + $columnGap;
            }

            $y = $stateY
                + $nodeH
                + ($rowSelfLoopHeight > 0.0 ? $globalNodeGap : 0.0)
                + $rowSelfLoopHeight
                + $rankGap;
        }

        $height = max(
            $this->_compactLayout ? 360.0 : 420.0,
            $y - $rankGap + ($this->_compactLayout ? 58.0 : 68.0)
        );

        return [
            'positions' => $positions,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return array<string,array{max_width:float,stack_height:float}>
     */
    private function verticalGlobalMetricsByTarget(): array
    {
        $result = [];
        foreach ($this->_transitions as $transition) {
            if (($transition['scope'] ?? '') !== 'global') {
                continue;
            }
            $target = (string) ($transition['to'] ?? '');
            if ($target === '' || !isset($this->_states[$target])) {
                continue;
            }
            $metrics = $this->verticalTransitionMetrics($transition);
            $result[$target] ??= [
                'max_width' => 0.0,
                'stack_height' => 0.0,
            ];
            $result[$target]['max_width'] = max(
                $result[$target]['max_width'],
                $metrics['width']
            );
            if ($result[$target]['stack_height'] > 0.0) {
                $result[$target]['stack_height'] += 7.0;
            }
            $result[$target]['stack_height'] += $metrics['height'];
        }
        return $result;
    }

    /**
     * @return array<string,array{max_width:float,stack_height:float}>
     */
    private function verticalSelfLoopMetricsByState(): array
    {
        $result = [];
        foreach ($this->_transitions as $transition) {
            if (($transition['scope'] ?? '') === 'global'
                || ($transition['from'] ?? '') !== ($transition['to'] ?? '')) {
                continue;
            }
            $state = (string) ($transition['to'] ?? '');
            if ($state === '' || !isset($this->_states[$state])) {
                continue;
            }
            $metrics = $this->verticalTransitionMetrics($transition);
            $result[$state] ??= [
                'max_width' => 0.0,
                'stack_height' => 0.0,
            ];
            $result[$state]['max_width'] = max(
                $result[$state]['max_width'],
                $metrics['width']
            );
            if ($result[$state]['stack_height'] > 0.0) {
                $result[$state]['stack_height'] += 7.0;
            }
            $result[$state]['stack_height'] += $metrics['height'];
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $transition
     * @return array{width:float,height:float}
     */
    private function verticalTransitionMetrics(array $transition): array
    {
        $label = $this->_transitionLabels[(string) ($transition['id'] ?? '')]
            ?? self::transitionLabel($transition);
        $parts = $this->transitionVisualParts($transition, $label);
        $lines = [$parts['signal']];
        foreach ($parts['guards'] as $guard) {
            $lines[] = '[' . $guard . ']';
        }
        if ($parts['effect'] !== '') {
            $lines[] = '/ ' . $parts['effect'];
        }
        $maxLength = 1;
        foreach ($lines as $line) {
            $maxLength = max(
                $maxLength,
                function_exists('mb_strlen')
                    ? mb_strlen($line, 'UTF-8')
                    : strlen($line)
            );
        }
        return [
            'width' => min(236.0, max(126.0, 22.0 + $maxLength * 6.15)),
            'height' => 10.0 + count($lines) * 15.0,
        ];
    }

    private function verticalGlobalStackOffset(string $transitionId, string $target): float
    {
        $offset = 0.0;
        foreach ($this->_transitions as $transition) {
            if (($transition['scope'] ?? '') !== 'global'
                || (string) ($transition['to'] ?? '') !== $target) {
                continue;
            }
            $id = (string) ($transition['id'] ?? '');
            if ($id === $transitionId) {
                break;
            }
            if ($offset > 0.0) {
                $offset += 7.0;
            }
            $offset += $this->verticalTransitionMetrics($transition)['height'];
        }
        if ($offset > 0.0) {
            $offset += 7.0;
        }
        return $offset;
    }

    private function verticalSelfLoopStackOffset(
        string $transitionId,
        string $state
    ): float {
        $offset = 0.0;
        foreach ($this->_transitions as $transition) {
            if (($transition['scope'] ?? '') === 'global'
                || (string) ($transition['from'] ?? '') !== $state
                || (string) ($transition['to'] ?? '') !== $state) {
                continue;
            }
            $id = (string) ($transition['id'] ?? '');
            if ($id === $transitionId) {
                break;
            }
            if ($offset > 0.0) {
                $offset += 7.0;
            }
            $offset += $this->verticalTransitionMetrics($transition)['height'];
        }
        if ($offset > 0.0) {
            $offset += 7.0;
        }
        return $offset;
    }

    /**
     * @param array{
     *   id:string,
     *   from:string,
     *   to:string,
     *   signal:string,
     *   guards:list<string>,
     *   actions:list<string>,
     *   runtime_operations:list<array<string,mixed>>,
     *   wildcard:bool,
     *   fallback:bool
     * } $transition
     * @param array<string,array{x:float,y:float,w:float,h:float,rank:int}> $positions
     */
    private function renderTransition(
        array $transition,
        array $positions,
        int $ordinal,
        int $total,
        int $sourceOrdinal = 0,
        int $sourceTotal = 1,
        int $targetOrdinal = 0,
        int $targetTotal = 1
    ): string {
        $to = $transition['to'];
        if (!isset($positions[$to])) {
            return '';
        }

        $signalType = self::signalType(
            (string) ($transition['signal_type'] ?? 'system')
        );
        $signalOrigin = self::signalOrigin(
            (string) ($transition['signal_origin'] ?? '')
        );
        $class = 'fsm-transition signal-type-' . $signalType
            . ' signal-origin-' . $signalOrigin;
        if ($transition['wildcard']) {
            $class .= ' wildcard';
        }
        if ($transition['fallback']) {
            $class .= ' fallback';
        }

        $id = $transition['id'];
        $semanticLabel = self::transitionLabel($transition);
        $label = $this->_transitionLabels[$transition['id']]
            ?? $semanticLabel;
        $toPos = $positions[$to];

        if ($this->_layoutDirection === 'vertical') {
            return $this->renderTransitionVertical(
                $transition,
                $positions,
                $ordinal,
                $total,
                $sourceOrdinal,
                $sourceTotal,
                $targetOrdinal,
                $targetTotal,
                $class,
                $id,
                $label,
                $semanticLabel
            );
        }

        if ($transition['from'] === '*') {
            $fromPoint = $this->globalSourcePoint($positions);
            $offset = ($ordinal - (($total - 1) / 2)) * 16.0;
            $x = $toPos['x'] + $toPos['w'] / 2 + $offset;
            $y1 = $fromPoint['y'];
            $y2 = $toPos['y'];
            $path = 'M' . self::n($x) . ' ' . self::n($y1)
                . ' L' . self::n($x) . ' ' . self::n($y2);
            $labelX = $x;
            $labelY = ($y1 + $y2) / 2 - 6.0;

            return $this->transitionSvg(
                $class,
                $id,
                $path,
                $label,
                $labelX,
                $labelY,
                $semanticLabel,
                $transition,
                $positions
            );
        }

        $from = $transition['from'];
        if (!isset($positions[$from])) {
            return '';
        }

        $fromPos = $positions[$from];

        if ($from === $to) {
            $loop = 44.0 + $ordinal * 30.0;
            $x = $fromPos['x'] + $fromPos['w'] * 0.68;
            $y = $fromPos['y'];
            $path = 'M' . self::n($x) . ' ' . self::n($y)
                . ' C'
                . self::n($x + $loop) . ' '
                . self::n($y - $loop)
                . ', '
                . self::n($x - $loop) . ' '
                . self::n($y - $loop)
                . ', '
                . self::n($x - 8) . ' '
                . self::n($y);
            $labelX = $x;
            $labelY = $y - $loop - 9.0;

            return $this->transitionSvg(
                $class . ' self-loop',
                $id,
                $path,
                $label,
                $labelX,
                $labelY,
                $semanticLabel,
                $transition,
                $positions
            );
        }

        $forward = $toPos['rank'] > $fromPos['rank'];
        $sameRank = $toPos['rank'] === $fromPos['rank'];
        $bidirectional = $this->hasReverseTransition($from, $to);
        if ($bidirectional) {
            $class .= ' bidirectional-edge';
        }

        $sourcePortY = $fromPos['y'] + $fromPos['h'] / 2;
        if ($sourceTotal > 1) {
            $sourcePortY = $fromPos['y']
                + 10.0
                + ($sourceOrdinal / max(1, $sourceTotal - 1))
                    * max(1.0, $fromPos['h'] - 20.0);
        }

        $targetPortY = $toPos['y'] + $toPos['h'] / 2;
        if ($targetTotal > 1) {
            $targetPortY = $toPos['y']
                + 10.0
                + ($targetOrdinal / max(1, $targetTotal - 1))
                    * max(1.0, $toPos['h'] - 20.0);
        }

        $laneRouting = $forward
            && ($toPos['rank'] - $fromPos['rank']) === 1
            && $sourceTotal > 1
            && !$bidirectional;

        if ($laneRouting) {
            $x1 = $fromPos['x'] + $fromPos['w'];
            $y1 = $sourcePortY;
            $x2 = $toPos['x'];
            $y2 = $targetPortY;

            $laneGap = $this->_compactLayout ? 28.0 : 32.0;
            $laneY = $fromPos['y']
                + $fromPos['h'] / 2
                + (
                    $sourceOrdinal
                    - (($sourceTotal - 1) / 2)
                ) * $laneGap;

            $horizontalGap = max(1.0, $x2 - $x1);
            $entryDistance = min(
                76.0,
                max(42.0, $horizontalGap * 0.28)
            );
            $exitDistance = min(
                86.0,
                max(48.0, $horizontalGap * 0.30)
            );

            $path = 'M' . self::n($x1) . ' ' . self::n($y1)
                . ' C'
                . self::n($x1 + $entryDistance) . ' '
                . self::n($laneY)
                . ', '
                . self::n($x2 - $exitDistance) . ' '
                . self::n($y2)
                . ', '
                . self::n($x2) . ' '
                . self::n($y2);

            $labelX = $x1 + min(
                112.0,
                max(72.0, $horizontalGap * 0.42)
            );
            $labelY = $laneY - 7.0;

            return $this->transitionSvg(
                $class,
                $id,
                $path,
                $label,
                $labelX,
                $labelY,
                $semanticLabel,
                $transition,
                $positions
            );
        }

        if ($forward) {
            $x1 = $fromPos['x'] + $fromPos['w'];
            $y1 = $sourcePortY;
            $x2 = $toPos['x'];
            $y2 = $targetPortY;
        } elseif ($sameRank) {
            $x1 = $fromPos['x'] + $fromPos['w'] / 2;
            $y1 = $fromPos['y'] + $fromPos['h'];
            $x2 = $toPos['x'] + $toPos['w'] / 2;
            $y2 = $toPos['y'];
        } else {
            $x1 = $fromPos['x'];
            $y1 = $sourcePortY;
            $x2 = $toPos['x'] + $toPos['w'];
            $y2 = $targetPortY;
        }

        $spread = ($ordinal - (($total - 1) / 2)) * 30.0;
        $distance = max(60.0, abs($x2 - $x1) * 0.44);
        $rankDistance = abs($toPos['rank'] - $fromPos['rank']);

        if ($bidirectional && !$sameRank) {
            /*
             * Reverse-pair transitions use opposite local corridors. A→B and
             * B→A therefore remain individually traceable instead of forming
             * one crossed ribbon between the same two states.
             */
            $upper = $forward;
            $corridorY = $upper
                ? min($y1, $y2) - 58.0 - abs($spread) * 0.35
                : max($y1, $y2) + 58.0 + abs($spread) * 0.35;
            $midX = ($x1 + $x2) / 2;
            $control = min(82.0, max(46.0, abs($x2 - $x1) * 0.22));

            $path = 'M' . self::n($x1) . ' ' . self::n($y1)
                . ' C'
                . self::n($forward ? $x1 + $control : $x1 - $control)
                . ' ' . self::n($corridorY)
                . ', '
                . self::n($midX) . ' ' . self::n($corridorY)
                . ', '
                . self::n($midX) . ' ' . self::n($corridorY)
                . ' C'
                . self::n($midX) . ' ' . self::n($corridorY)
                . ', '
                . self::n($forward ? $x2 - $control : $x2 + $control)
                . ' ' . self::n($corridorY)
                . ', '
                . self::n($x2) . ' ' . self::n($y2);
            $labelX = $midX;
            $labelY = $corridorY - 8.0;

            return $this->transitionSvg(
                $class . (!$forward ? ' return-edge' : ''),
                $id,
                $path,
                $label,
                $labelX,
                $labelY,
                $semanticLabel,
                $transition,
                $positions
            );
        }

        if ($forward && !$sameRank && $rankDistance >= 2) {
            /*
             * A deliberately long forward branch is routed outside the main
             * rank corridor. This is useful for authentication/account side
             * branches without cutting across the application workflow.
             */
            $minY = min(array_map(
                static fn (array $position): float => $position['y'],
                $positions
            ));
            $maxY = max(array_map(
                static fn (array $position): float =>
                    $position['y'] + $position['h'],
                $positions
            ));
            $fromCenterY = $fromPos['y'] + $fromPos['h'] / 2;
            $toCenterY = $toPos['y'] + $toPos['h'] / 2;
            $useTop = $toCenterY <= $fromCenterY;
            $corridorY = $useTop
                ? max(76.0, $minY - 54.0 - $ordinal * 30.0)
                : $maxY + 54.0 + $ordinal * 30.0;
            $x1 = $fromPos['x'] + $fromPos['w'] * 0.72;
            $y1 = $useTop
                ? $fromPos['y']
                : $fromPos['y'] + $fromPos['h'];
            $x2 = $toPos['x'] + $toPos['w'] * 0.28;
            $y2 = $useTop
                ? $toPos['y']
                : $toPos['y'] + $toPos['h'];
            $midX = ($x1 + $x2) / 2;
            $verticalControl = 64.0;

            $path = 'M' . self::n($x1) . ' ' . self::n($y1)
                . ' C'
                . self::n($x1) . ' '
                . self::n($useTop ? $y1 - $verticalControl : $y1 + $verticalControl)
                . ', '
                . self::n($x1) . ' ' . self::n($corridorY)
                . ', '
                . self::n($midX) . ' ' . self::n($corridorY)
                . ' C'
                . self::n($x2) . ' ' . self::n($corridorY)
                . ', '
                . self::n($x2) . ' '
                . self::n($useTop ? $y2 - $verticalControl : $y2 + $verticalControl)
                . ', '
                . self::n($x2) . ' ' . self::n($y2);
            $labelX = $midX;
            $labelY = $corridorY - 8.0;

            return $this->transitionSvg(
                $class . ' outer-forward',
                $id,
                $path,
                $label,
                $labelX,
                $labelY,
                $semanticLabel,
                $transition,
                $positions
            );
        }

        if (!$forward && !$sameRank && $rankDistance >= 2) {
            /*
             * Long returns use an outer top/bottom corridor and enter states
             * vertically. They do not cut through the right-hand forward
             * fan-out ports of their targets.
             */
            $minY = min(array_map(
                static fn (array $position): float => $position['y'],
                $positions
            ));
            $maxY = max(array_map(
                static fn (array $position): float =>
                    $position['y'] + $position['h'],
                $positions
            ));
            $fromCenterY = $fromPos['y'] + $fromPos['h'] / 2;
            $toCenterY = $toPos['y'] + $toPos['h'] / 2;
            $useTop = $fromCenterY <= $toCenterY;
            $laneOrdinal = max($ordinal, $targetOrdinal);
            $corridorY = $useTop
                ? max(76.0, $minY - 46.0 - $laneOrdinal * 30.0)
                : $maxY + 48.0 + $laneOrdinal * 30.0;

            $sourceFraction = $sourceTotal <= 1
                ? 0.5
                : 0.18 + 0.64 * (
                    $sourceOrdinal / max(1, $sourceTotal - 1)
                );
            $targetFraction = $targetTotal <= 1
                ? 0.5
                : 0.18 + 0.64 * (
                    $targetOrdinal / max(1, $targetTotal - 1)
                );
            $x1 = $fromPos['x'] + $fromPos['w'] * $sourceFraction;
            $y1 = $useTop
                ? $fromPos['y']
                : $fromPos['y'] + $fromPos['h'];
            $x2 = $toPos['x'] + $toPos['w'] * $targetFraction;
            $y2 = $useTop
                ? $toPos['y']
                : $toPos['y'] + $toPos['h'];
            $midX = ($x1 + $x2) / 2;
            $verticalControl = 62.0;

            $path = 'M' . self::n($x1) . ' ' . self::n($y1)
                . ' C'
                . self::n($x1) . ' '
                . self::n($useTop ? $y1 - $verticalControl : $y1 + $verticalControl)
                . ', '
                . self::n($x1) . ' ' . self::n($corridorY)
                . ', '
                . self::n($midX) . ' ' . self::n($corridorY)
                . ' C'
                . self::n($x2) . ' ' . self::n($corridorY)
                . ', '
                . self::n($x2) . ' '
                . self::n($useTop ? $y2 - $verticalControl : $y2 + $verticalControl)
                . ', '
                . self::n($x2) . ' ' . self::n($y2);
            $labelX = $midX;
            $labelY = $corridorY - 8.0;

            return $this->transitionSvg(
                $class . ' return-edge outer-return',
                $id,
                $path,
                $label,
                $labelX,
                $labelY,
                $semanticLabel,
                $transition,
                $positions
            );
        }

        if ($sameRank
            && abs($fromPos['x'] - $toPos['x']) < 1.0) {
            /*
             * States in the same ranked column connect around the column,
             * not above the upper node. This removes a major source of
             * collisions between same-rank returns and state self-loops.
             */
            $leftX = min($fromPos['x'], $toPos['x']);
            $sideX = $leftX - 74.0 - abs($spread);
            $x1 = $fromPos['x'];
            $y1 = $fromPos['y'] + $fromPos['h'] / 2;
            $x2 = $toPos['x'];
            $y2 = $toPos['y'] + $toPos['h'] / 2;

            $path = 'M' . self::n($x1) . ' ' . self::n($y1)
                . ' C'
                . self::n($sideX) . ' ' . self::n($y1)
                . ', '
                . self::n($sideX) . ' ' . self::n($y2)
                . ', '
                . self::n($x2) . ' ' . self::n($y2);
            $labelX = $sideX;
            $labelY = ($y1 + $y2) / 2 - 8.0;
        } elseif ($sameRank) {
            $controlY = min($y1, $y2) - 62.0 - abs($spread);
            $path = 'M' . self::n($x1) . ' ' . self::n($y1)
                . ' C'
                . self::n($x1 + $spread) . ' '
                . self::n($controlY)
                . ', '
                . self::n($x2 + $spread) . ' '
                . self::n($controlY)
                . ', '
                . self::n($x2) . ' '
                . self::n($y2);
            $labelX = ($x1 + $x2) / 2 + $spread;
            $labelY = $controlY - 8.0;
        } else {
            $curveY = $spread;
            if (!$forward) {
                $targetSpread = (
                    $targetOrdinal
                    - (($targetTotal - 1) / 2)
                ) * 16.0;
                $curveY += $targetSpread - 34.0;
            }
            $path = 'M' . self::n($x1) . ' ' . self::n($y1)
                . ' C'
                . self::n(
                    $forward
                        ? $x1 + $distance
                        : $x1 - $distance
                )
                . ' '
                . self::n($y1 + $curveY)
                . ', '
                . self::n(
                    $forward
                        ? $x2 - $distance
                        : $x2 + $distance
                )
                . ' '
                . self::n($y2 + $curveY)
                . ', '
                . self::n($x2) . ' '
                . self::n($y2);
            $labelX = ($x1 + $x2) / 2;
            $labelY = ($y1 + $y2) / 2 + $curveY - 10.0;
        }

        return $this->transitionSvg(
            $class . (!$forward ? ' return-edge' : ''),
            $id,
            $path,
            $label,
            $labelX,
            $labelY,
            $semanticLabel,
            $transition,
            $positions
        );
    }

    /**
     * Vertical EFSM routing keeps the main workflow top-to-bottom. Forward
     * transitions use the inter-rank corridor; returns and long jumps use
     * bounded side lanes so they do not cut through the central workflow.
     *
     * @param array<string,mixed> $transition
     * @param array<string,array{x:float,y:float,w:float,h:float,rank:int}> $positions
     */
    private function renderTransitionVertical(
        array $transition,
        array $positions,
        int $ordinal,
        int $total,
        int $sourceOrdinal,
        int $sourceTotal,
        int $targetOrdinal,
        int $targetTotal,
        string $class,
        string $id,
        string $label,
        string $semanticLabel
    ): string {
        $to = $transition['to'];
        $toPos = $positions[$to];

        if (($transition['scope'] ?? '') === 'global') {
            $metrics = $this->verticalTransitionMetrics($transition);
            $targetMetrics = $this->verticalGlobalMetricsByTarget()[$to] ?? [
                'max_width' => $metrics['width'],
                'stack_height' => $metrics['height'],
            ];
            $stackOffset = $this->verticalGlobalStackOffset($id, $to);
            $cardGap = $this->_compactLayout ? 12.0 : 14.0;
            $labelX = $toPos['x'] + $toPos['w'] / 2;
            $stackTop = $toPos['y']
                - $cardGap
                - (float) $targetMetrics['stack_height'];
            $labelY = $stackTop
                + $stackOffset
                + $metrics['height'] / 2;

            /*
             * A finite global transition has no single local source. Cards are
             * therefore stacked as a compact ingress set above their target.
             * Only the lowest card draws the shared short arrow into the state;
             * no fake page-wide rail is introduced.
             */
            $isLowest = $stackOffset + $metrics['height']
                >= (float) $targetMetrics['stack_height'] - 0.1;
            $path = '';
            if ($isLowest) {
                $x = $toPos['x'] + $toPos['w'] / 2;
                $y1 = $labelY + $metrics['height'] / 2;
                $y2 = $toPos['y'];
                $midY = ($y1 + $y2) / 2;
                $path = 'M' . self::n($x) . ' ' . self::n($y1)
                    . ' C' . self::n($x) . ' ' . self::n($midY)
                    . ', ' . self::n($x) . ' ' . self::n($midY)
                    . ', ' . self::n($x) . ' ' . self::n($y2);
            }

            $fromStates = array_values(array_filter(
                (array) ($transition['from_states'] ?? []),
                'is_string'
            ));
            $scopeTitle = $semanticLabel . ' {global from '
                . implode(', ', $fromStates) . '}';
            return $this->transitionSvg(
                $class . ' global-scope',
                $id,
                $path,
                $label,
                $labelX,
                $labelY,
                $scopeTitle,
                $transition,
                $positions
            );
        }

        if ($transition['from'] === '*') {
            $fromPoint = $this->globalSourcePoint($positions);
            $x2 = $toPos['x'] + $toPos['w'] / 2;
            $y2 = $toPos['y'];
            $path = 'M' . self::n($fromPoint['x']) . ' '
                . self::n($fromPoint['y'])
                . ' C' . self::n($fromPoint['x']) . ' '
                . self::n(($fromPoint['y'] + $y2) / 2)
                . ', ' . self::n($x2) . ' '
                . self::n(($fromPoint['y'] + $y2) / 2)
                . ', ' . self::n($x2) . ' ' . self::n($y2);
            return $this->transitionSvg(
                $class,
                $id,
                $path,
                $label,
                ($fromPoint['x'] + $x2) / 2,
                ($fromPoint['y'] + $y2) / 2,
                $semanticLabel,
                $transition,
                $positions
            );
        }

        $from = $transition['from'];
        if (!isset($positions[$from])) {
            return '';
        }
        $fromPos = $positions[$from];

        if ($from === $to) {
            $metrics = $this->verticalTransitionMetrics($transition);
            $offset = $this->verticalSelfLoopStackOffset($id, $to);
            $gap = $this->_compactLayout ? 12.0 : 14.0;
            $labelX = $fromPos['x'] + $fromPos['w'] / 2;
            $labelY = $fromPos['y']
                + $fromPos['h']
                + $gap
                + $offset
                + $metrics['height'] / 2;
            return $this->transitionSvg(
                $class . ' self-loop compact-self-scope',
                $id,
                '',
                $label,
                $labelX,
                $labelY,
                $semanticLabel . ' {self}',
                $transition,
                $positions
            );
        }

        $forward = $toPos['rank'] > $fromPos['rank'];
        $sameRank = $toPos['rank'] === $fromPos['rank'];
        $rankDistance = abs($toPos['rank'] - $fromPos['rank']);
        $bidirectional = $this->hasReverseTransition($from, $to);
        if ($bidirectional) {
            $class .= ' bidirectional-edge';
        }

        $sourcePortX = $fromPos['x'] + $fromPos['w'] / 2;
        if ($sourceTotal > 1) {
            $sourcePortX = $fromPos['x'] + 12.0
                + ($sourceOrdinal / max(1, $sourceTotal - 1))
                    * max(1.0, $fromPos['w'] - 24.0);
        }
        $targetPortX = $toPos['x'] + $toPos['w'] / 2;
        if ($targetTotal > 1) {
            $targetPortX = $toPos['x'] + 12.0
                + ($targetOrdinal / max(1, $targetTotal - 1))
                    * max(1.0, $toPos['w'] - 24.0);
        }

        if ($sameRank) {
            $rightward = $toPos['x'] > $fromPos['x'];
            $x1 = $rightward
                ? $fromPos['x'] + $fromPos['w']
                : $fromPos['x'];
            $x2 = $rightward
                ? $toPos['x']
                : $toPos['x'] + $toPos['w'];
            $y1 = $fromPos['y'] + $fromPos['h'] / 2;
            $y2 = $toPos['y'] + $toPos['h'] / 2;
            $laneY = min($fromPos['y'], $toPos['y'])
                - 54.0 - $ordinal * 22.0;
            $path = 'M' . self::n($x1) . ' ' . self::n($y1)
                . ' C' . self::n($x1) . ' ' . self::n($laneY)
                . ', ' . self::n($x2) . ' ' . self::n($laneY)
                . ', ' . self::n($x2) . ' ' . self::n($y2);
            return $this->transitionSvg(
                $class,
                $id,
                $path,
                $label,
                ($x1 + $x2) / 2,
                $laneY - 8.0,
                $semanticLabel,
                $transition,
                $positions
            );
        }

        if ($forward && $rankDistance === 1 && !$bidirectional) {
            $x1 = $sourcePortX;
            $y1 = $fromPos['y'] + $fromPos['h'];
            $x2 = $targetPortX;
            $y2 = $toPos['y'];
            $spread = ($ordinal - (($total - 1) / 2)) * 18.0;
            $midY = ($y1 + $y2) / 2 + $spread;
            $path = 'M' . self::n($x1) . ' ' . self::n($y1)
                . ' C' . self::n($x1) . ' ' . self::n($midY)
                . ', ' . self::n($x2) . ' ' . self::n($midY)
                . ', ' . self::n($x2) . ' ' . self::n($y2);
            return $this->transitionSvg(
                $class,
                $id,
                $path,
                $label,
                ($x1 + $x2) / 2,
                $midY,
                $semanticLabel,
                $transition,
                $positions
            );
        }

        $minX = min(array_map(
            static fn (array $position): float => $position['x'],
            $positions
        ));
        $maxX = max(array_map(
            static fn (array $position): float =>
                $position['x'] + $position['w'],
            $positions
        ));
        $laneOrdinal = max($ordinal, $sourceOrdinal, $targetOrdinal);
        $laneOffset = 34.0 + min(8, $laneOrdinal) * 12.0;
        $corridorX = max(18.0, $minX - $laneOffset);

        $x1 = $sourcePortX;
        $y1 = $forward
            ? $fromPos['y'] + $fromPos['h']
            : $fromPos['y'];
        $x2 = $targetPortX;
        $y2 = $forward
            ? $toPos['y']
            : $toPos['y'] + $toPos['h'];
        $sourceTurnY = $forward ? $y1 + 54.0 : $y1 - 54.0;
        $targetTurnY = $forward ? $y2 - 54.0 : $y2 + 54.0;
        $path = 'M' . self::n($x1) . ' ' . self::n($y1)
            . ' C' . self::n($x1) . ' ' . self::n($sourceTurnY)
            . ', ' . self::n($corridorX) . ' ' . self::n($sourceTurnY)
            . ', ' . self::n($corridorX) . ' ' . self::n($sourceTurnY)
            . ' L' . self::n($corridorX) . ' ' . self::n($targetTurnY)
            . ' C' . self::n($corridorX) . ' ' . self::n($targetTurnY)
            . ', ' . self::n($x2) . ' ' . self::n($targetTurnY)
            . ', ' . self::n($x2) . ' ' . self::n($y2);

        return $this->transitionSvg(
            $class . (!$forward ? ' return-edge' : ' outer-forward'),
            $id,
            $path,
            $label,
            $corridorX,
            ($sourceTurnY + $targetTurnY) / 2,
            $semanticLabel,
            $transition,
            $positions
        );
    }

    private function hasReverseTransition(string $from, string $to): bool
    {
        if ($from === '' || $to === '' || $from === $to) {
            return false;
        }

        foreach ($this->_transitions as $transition) {
            if ($transition['from'] === $to
                && $transition['to'] === $from) {
                return true;
            }
        }

        return false;
    }


    /**
     * Persisted local transition geometry is presentation-only, but it must
     * still satisfy the current node topology. A path is reusable only while
     * its first point touches the source state box and its last point touches
     * the target state box. Stale/orphan paths therefore fall back to the
     * deterministic router and are later replaced in the persisted snapshot.
     *
     * @param array<string,mixed> $geometry
     * @param array<string,mixed> $transition
     * @param array<string,array{x:float,y:float,w:float,h:float,rank:int}> $positions
     */
    private function persistedTransitionGeometryAnchored(
        array $geometry,
        array $transition,
        array $positions
    ): bool {
        if (($transition['scope'] ?? '') === 'global') {
            return false;
        }

        $from = trim((string) ($transition['from'] ?? ''));
        $to = trim((string) ($transition['to'] ?? ''));
        if ($from === ''
            || $to === ''
            || $from === $to
            || !isset($positions[$from], $positions[$to])) {
            return false;
        }

        $path = trim((string) ($geometry['path'] ?? ''));
        $endpoints = self::svgPathEndpoints($path);
        if ($endpoints === null) {
            return false;
        }

        return self::pointOnStateBoundary(
            $endpoints['start_x'],
            $endpoints['start_y'],
            $positions[$from]
        ) && self::pointOnStateBoundary(
            $endpoints['end_x'],
            $endpoints['end_y'],
            $positions[$to]
        );
    }

    /**
     * OPUS-generated persisted edge paths use absolute M/L/C commands. Keep
     * parsing deliberately strict so malformed or unsupported geometry cannot
     * be mistaken for a valid anchored path.
     *
     * @return array{start_x:float,start_y:float,end_x:float,end_y:float}|null
     */
    private static function svgPathEndpoints(string $path): ?array
    {
        $path = trim($path);
        if ($path === '' || preg_match('/\\A\\s*M\\s*/D', $path) !== 1) {
            return null;
        }

        $number = '[-+]?(?:\\d+(?:\\.\\d*)?|\\.\\d+)(?:[eE][-+]?\\d+)?';
        $commandsOnly = preg_replace('/' . $number . '/', '', $path);
        if (!is_string($commandsOnly)
            || preg_match('/\\A[\\s,MLC]+\\z/D', $commandsOnly) !== 1) {
            return null;
        }

        if (preg_match_all('/' . $number . '/', $path, $matches) !== 1
            && count($matches[0] ?? []) < 4) {
            return null;
        }
        $values = $matches[0] ?? [];
        if (count($values) < 4) {
            return null;
        }

        $startX = (float) $values[0];
        $startY = (float) $values[1];
        $endX = (float) $values[count($values) - 2];
        $endY = (float) $values[count($values) - 1];
        foreach ([$startX, $startY, $endX, $endY] as $value) {
            if (!is_finite($value)) {
                return null;
            }
        }

        return [
            'start_x' => $startX,
            'start_y' => $startY,
            'end_x' => $endX,
            'end_y' => $endY,
        ];
    }

    /**
     * @param array{x:float,y:float,w:float,h:float,rank:int} $box
     */
    private static function pointOnStateBoundary(
        float $x,
        float $y,
        array $box
    ): bool {
        $tolerance = 3.0;
        $left = (float) $box['x'];
        $top = (float) $box['y'];
        $right = $left + (float) $box['w'];
        $bottom = $top + (float) $box['h'];
        $insideX = $x >= $left - $tolerance && $x <= $right + $tolerance;
        $insideY = $y >= $top - $tolerance && $y <= $bottom + $tolerance;
        if (!$insideX || !$insideY) {
            return false;
        }

        return abs($x - $left) <= $tolerance
            || abs($x - $right) <= $tolerance
            || abs($y - $top) <= $tolerance
            || abs($y - $bottom) <= $tolerance;
    }

    /**
     * @param array<string,mixed> $transition
     */
    private function transitionSvg(
        string $class,
        string $id,
        string $path,
        string $label,
        float $labelX,
        float $labelY,
        string $semanticLabel,
        array $transition,
        array $positions
    ): string {
        if ($this->_layoutDirection !== 'vertical') {
            return $this->transitionSvgHorizontal(
                $class,
                $id,
                $path,
                $label,
                $labelX,
                $labelY,
                $semanticLabel,
                $transition,
                $positions
            );
        }

        $parts = $this->transitionVisualParts($transition, $label);
        $lines = [$parts['signal']];
        foreach ($parts['guards'] as $guard) {
            $lines[] = '[' . $guard . ']';
        }
        if ($parts['effect'] !== '') {
            $lines[] = '/ ' . $parts['effect'];
        }

        $maxLength = 1;
        foreach ($lines as $line) {
            $maxLength = max(
                $maxLength,
                function_exists('mb_strlen')
                    ? mb_strlen($line, 'UTF-8')
                    : strlen($line)
            );
        }
        $lineHeight = 15.0;
        $labelWidth = min(
            236.0,
            max(126.0, 22.0 + $maxLength * 6.15)
        );
        $labelHeight = 10.0 + count($lines) * $lineHeight;

        $anchorX = $labelX;
        $anchorY = $labelY;
        $fixedCard = (($transition['scope'] ?? '') === 'global')
            || (($transition['from'] ?? '') === ($transition['to'] ?? ''));
        $persistedGeometry = $this->_persistedTransitionGeometry[$id] ?? null;
        $persistedPathAnchored = !$fixedCard
            && is_array($persistedGeometry)
            && $this->persistedTransitionGeometryAnchored(
                $persistedGeometry,
                $transition,
                $positions
            );
        if ($persistedPathAnchored) {
            $path = (string) ($persistedGeometry['path'] ?? $path);
        }
        if (is_array($persistedGeometry)) {
            $labelX = (float) ($persistedGeometry['label_x'] ?? $labelX);
            $labelY = (float) ($persistedGeometry['label_y'] ?? $labelY);
        } elseif (!$fixedCard) {
            [$labelX, $labelY] = $this->reserveVerticalTransitionLabel(
                $labelX,
                $labelY,
                $labelWidth,
                $labelHeight
            );
        }

        $labelLeaderPath = '';
        $labelLeader = '';
        {
            $leaderDistance = hypot($labelX - $anchorX, $labelY - $anchorY);
            if ($leaderDistance >= 18.0) {
                $labelLeaderPath = 'M' . self::n($anchorX) . ' ' . self::n($anchorY)
                    . ' L' . self::n($labelX) . ' ' . self::n($labelY);
                $labelLeader = '<path class="fsm-label-leader" d="'
                    . self::h($labelLeaderPath) . '" />';
            }
        }

        $link = $this->_transitionLinks[$id] ?? null;
        $postAction = $this->_transitionActions[$id] ?? null;
        $getActionable = is_string($link) && $link !== '';
        $postActionable = is_array($postAction);
        $actionable = $getActionable || $postActionable;
        if ($actionable) {
            $class .= ' actionable';
        }

        $boxTop = $labelY - $labelHeight / 2;
        $labelSvg = '<g class="fsm-edge-label-box">'
            . '<rect class="fsm-edge-label-bg" x="'
            . self::n($labelX - $labelWidth / 2)
            . '" y="' . self::n($boxTop)
            . '" width="' . self::n($labelWidth)
            . '" height="' . self::n($labelHeight)
            . '" rx="6"'
            . ' fill="var(--opus-fsm-label-halo,#07111f)"'
            . ' fill-opacity=".95"'
            . ' stroke="var(--opus-fsm-node-border,#6b829e)"'
            . ' stroke-width=".7" />';

        $cursorY = $boxTop + 17.0;
        $labelSvg .= '<text class="fsm-edge-label fsm-edge-signal" x="'
            . self::n($labelX) . '" y="' . self::n($cursorY) . '">'
            . self::h($parts['signal']) . '</text>';
        foreach ($parts['guards'] as $guard) {
            $cursorY += $lineHeight;
            $labelSvg .= '<text class="fsm-edge-label fsm-edge-guard" x="'
                . self::n($labelX) . '" y="' . self::n($cursorY) . '">['
                . self::h($guard) . ']</text>';
        }
        if ($parts['effect'] !== '') {
            $cursorY += $lineHeight;
            $labelSvg .= '<text class="fsm-edge-label fsm-edge-effect" x="'
                . self::n($labelX) . '" y="' . self::n($cursorY) . '">/ '
                . self::h($parts['effect']) . '</text>';
        }
        $scopeBadge = (($transition['scope'] ?? '') === 'global')
            ? 'global'
            : ((($transition['from'] ?? '') === ($transition['to'] ?? ''))
                ? 'self'
                : '');
        if ($scopeBadge !== '') {
            $labelSvg .= '<text class="fsm-edge-scope" x="'
                . self::n($labelX + $labelWidth / 2 - 7.0)
                . '" y="' . self::n($boxTop + 10.0) . '">'
                . self::h($scopeBadge) . '</text>';
        }
        $labelSvg .= '</g>';

        if ($getActionable) {
            $labelSvg = '<a class="fsm-signal-link" href="'
                . self::h($link)
                . '" aria-label="' . self::h($semanticLabel)
                . '" role="link" tabindex="0" focusable="true">'
                . $labelSvg . '</a>';
        } elseif ($postActionable) {
            $labelSvg .= $this->postActionForeignObject(
                $postAction,
                $semanticLabel,
                $labelX - $labelWidth / 2,
                $boxTop,
                $labelWidth,
                $labelHeight
            );
        }
        $labelSvg = $this->signalCardSvg(
            $labelSvg,
            $labelX,
            $labelY,
            $labelWidth,
            $labelHeight
        );

        $origin = str_contains($class, 'signal-origin-user')
            ? 'user'
            : (str_contains($class, 'signal-origin-automatic')
                ? 'automatic'
                : 'unspecified');

        $edgeSvg = $path === ''
            ? ''
            : '<path class="fsm-edge" d="' . self::h($path)
                . '" marker-end="url(#fsm-arrow-' . self::h($origin) . ')" />';

        $this->_renderedTransitionGeometry[$id] = [
            'path' => $path,
            'label_x' => $labelX,
            'label_y' => $labelY,
            'leader_path' => $labelLeaderPath,
        ];

        return '<g class="' . self::h($class)
            . '" data-transition-id="' . self::h($id)
            . '" data-signal-origin="' . self::h($origin)
            . '"' . $this->transitionLayoutAttributes($transition) . '>'
            . '<title>' . self::h($semanticLabel) . '</title>'
            . $edgeSvg
            . $labelLeader
            . $labelSvg
            . '</g>';
    }

    /** @param array<string,mixed> $transition */
    private function transitionSvgHorizontal(
        string $class,
        string $id,
        string $path,
        string $label,
        float $labelX,
        float $labelY,
        string $semanticLabel,
        array $transition,
        array $positions
    ): string {
        $length = function_exists('mb_strlen')
            ? mb_strlen($label, 'UTF-8')
            : strlen($label);
        $labelWidth = min(
            260.0,
            max(56.0, 20.0 + $length * 7.1)
        );
        $labelHeight = 20.0;

        $anchorX = $labelX;
        $anchorY = $labelY;
        $fixedCard = (($transition['scope'] ?? '') === 'global')
            || (($transition['from'] ?? '') === ($transition['to'] ?? ''));
        $persistedGeometry = $this->_persistedTransitionGeometry[$id] ?? null;
        $persistedPathAnchored = !$fixedCard
            && is_array($persistedGeometry)
            && $this->persistedTransitionGeometryAnchored(
                $persistedGeometry,
                $transition,
                $positions
            );
        if ($persistedPathAnchored) {
            $path = (string) ($persistedGeometry['path'] ?? $path);
        }
        if (is_array($persistedGeometry)) {
            $labelX = (float) ($persistedGeometry['label_x'] ?? $labelX);
            $labelY = (float) ($persistedGeometry['label_y'] ?? $labelY);
        } else {
            [$labelX, $labelY] = $this->reserveTransitionLabel(
                $labelX,
                $labelY,
                $labelWidth,
                $labelHeight
            );
        }

        $labelLeaderPath = '';
        $labelLeader = '';
        {
            $leaderDistance = hypot($labelX - $anchorX, $labelY - $anchorY);
            if ($leaderDistance >= 18.0) {
                $labelLeaderPath = 'M' . self::n($anchorX) . ' ' . self::n($anchorY - 5.0)
                    . ' L' . self::n($labelX) . ' ' . self::n($labelY - 5.0);
                $labelLeader = '<path class="fsm-label-leader" d="'
                    . self::h($labelLeaderPath) . '" />';
            }
        }

        $link = $this->_transitionLinks[$id] ?? null;
        $postAction = $this->_transitionActions[$id] ?? null;
        $getActionable = is_string($link) && $link !== '';
        $postActionable = is_array($postAction);
        $actionable = $getActionable || $postActionable;
        if ($actionable) {
            $class .= ' actionable';
        }

        $labelSvg = '<g class="fsm-edge-label-box">'
            . '<rect class="fsm-edge-label-bg" x="'
            . self::n($labelX - $labelWidth / 2)
            . '" y="'
            . self::n($labelY - 14.0)
            . '" width="'
            . self::n($labelWidth)
            . '" height="'
            . self::n($labelHeight)
            . '" rx="4"'
            . ' fill="var(--opus-fsm-label-halo,#07111f)"'
            . ' fill-opacity=".94"'
            . ' stroke="var(--opus-fsm-node-border,#6b829e)"'
            . ' stroke-width=".7" />'
            . '<text class="fsm-edge-label" x="'
            . self::n($labelX)
            . '" y="'
            . self::n($labelY)
            . '">'
            . self::h($label)
            . '</text>'
            . '</g>';

        if ($getActionable) {
            $labelSvg = '<a class="fsm-signal-link" href="'
                . self::h($link)
                . '" aria-label="'
                . self::h($label)
                . '" role="link" tabindex="0" focusable="true">'
                . $labelSvg
                . '</a>';
        } elseif ($postActionable) {
            $labelSvg .= $this->postActionForeignObject(
                $postAction,
                $label,
                $labelX - $labelWidth / 2,
                $labelY - 14.0,
                $labelWidth,
                $labelHeight
            );
        }
        $labelSvg = $this->signalCardSvg(
            $labelSvg,
            $labelX,
            $labelY,
            $labelWidth,
            $labelHeight
        );

        $origin = str_contains($class, 'signal-origin-user')
            ? 'user'
            : (str_contains($class, 'signal-origin-automatic')
                ? 'automatic'
                : 'unspecified');

        $this->_renderedTransitionGeometry[$id] = [
            'path' => $path,
            'label_x' => $labelX,
            'label_y' => $labelY,
            'leader_path' => $labelLeaderPath,
        ];

        return '<g class="' . self::h($class)
            . '" data-transition-id="' . self::h($id)
            . '" data-signal-origin="' . self::h($origin)
            . '"' . $this->transitionLayoutAttributes($transition) . '>'
            . '<title>' . self::h($semanticLabel) . '</title>'
            . '<path class="fsm-edge" d="' . self::h($path)
            . '" marker-end="url(#fsm-arrow-' . self::h($origin) . ')" />'
            . $labelLeader
            . $labelSvg
            . '</g>';
    }

    /** @param array<string,mixed> $transition */
    private function transitionLayoutAttributes(array $transition): string
    {
        $from = trim((string) ($transition['from'] ?? ''));
        $to = trim((string) ($transition['to'] ?? ''));
        $scope = trim((string) ($transition['scope'] ?? ''));
        $anchor = ($scope === 'global' || ($from !== '' && $from === $to))
            ? $to
            : '';

        return ' data-from-state="' . self::h($from) . '"'
            . ' data-to-state="' . self::h($to) . '"'
            . ' data-transition-scope="' . self::h($scope) . '"'
            . ' data-anchor-state="' . self::h($anchor) . '"';
    }

    /**
     * Keep technical EFSM semantics visually separable without translating
     * canonical IDs. Custom transition labels remain one signal line.
     *
     * @param array<string,mixed> $transition
     * @return array{signal:string,guards:list<string>,effect:string}
     */
    private function transitionVisualParts(array $transition, string $label): array
    {
        $semantic = self::transitionLabel($transition);
        if ($label !== $semantic) {
            return [
                'signal' => $label,
                'guards' => [],
                'effect' => '',
            ];
        }

        $signal = match ((string) ($transition['signal'] ?? '')) {
            '__any__' => '*',
            '__default__' => 'default',
            default => (string) ($transition['signal'] ?? ''),
        };
        $guards = array_values(array_filter(
            (array) ($transition['guards'] ?? []),
            'is_string'
        ));
        $effects = [];
        foreach ((array) ($transition['actions'] ?? []) as $action) {
            if (is_string($action) && trim($action) !== '') {
                $effects[] = self::callLabel($action);
            }
        }
        foreach ((array) ($transition['runtime_operations'] ?? []) as $operation) {
            if (!is_array($operation)) {
                continue;
            }
            $op = trim((string) ($operation['op'] ?? ''));
            if ($op === '') {
                continue;
            }
            $name = trim((string) ($operation['name'] ?? ''));
            $effects[] = $op . ($name !== '' ? '(' . $name . ')' : '()');
        }

        return [
            'signal' => $signal,
            'guards' => $guards,
            'effect' => implode('; ', $effects),
        ];
    }

    /**
     * One signal card contains the signal plus its guards/effects. In writable
     * DEV mode the whole presentation object can be moved with the right mouse
     * button; FSM semantics and transition endpoints remain untouched.
     */
    private function signalCardSvg(
        string $content,
        float $labelX,
        float $labelY,
        float $width,
        float $height
    ): string {
        $draggable = (($this->_layoutPersistence['writable'] ?? false) === true)
            ? '1'
            : '0';

        return '<g class="fsm-signal-card" data-layout-signal-draggable="'
            . $draggable
            . '" data-signal-x="' . self::n($labelX)
            . '" data-signal-y="' . self::n($labelY)
            . '" data-signal-w="' . self::n($width)
            . '" data-signal-h="' . self::n($height)
            . '">' . $content . '</g>';
    }

    /**
     * @param array{method:string,url:string,fields:array<string,string>} $action
     */
    private function postActionForeignObject(
        array $action,
        string $label,
        float $x,
        float $y,
        float $width,
        float $height
    ): string {
        $fields = '';
        foreach ($action['fields'] as $name => $value) {
            $fields .= '<input type="hidden" name="'
                . self::h($name)
                . '" value="'
                . self::h($value)
                . '" />';
        }

        return '<foreignObject class="fsm-signal-post-object" x="'
            . self::n($x)
            . '" y="'
            . self::n($y)
            . '" width="'
            . self::n($width)
            . '" height="'
            . self::n($height)
            . '"><form xmlns="http://www.w3.org/1999/xhtml"'
            . ' class="fsm-signal-post-form" method="post" action="'
            . self::h($action['url'])
            . '">'
            . $fields
            . '<button type="submit" class="fsm-signal-post-submit"'
            . ' formnovalidate aria-label="'
            . self::h($label)
            . '" title="'
            . self::h($label)
            . '"></button></form></foreignObject>';
    }

    /**
     * Reserve every deterministic global ingress card before local transition
     * labels are placed. Local labels can then route around those cards.
     *
     * @param array<string,array{x:float,y:float,w:float,h:float,rank:int}> $positions
     */
    private function reserveVerticalFixedTransitionBoxes(array $positions): void
    {
        $globalMetrics = $this->verticalGlobalMetricsByTarget();
        foreach ($this->_transitions as $transition) {
            $to = (string) ($transition['to'] ?? '');
            if ($to === '' || !isset($positions[$to])) {
                continue;
            }
            $id = (string) ($transition['id'] ?? '');
            $metrics = $this->verticalTransitionMetrics($transition);
            $position = $positions[$to];
            $gap = $this->_compactLayout ? 12.0 : 14.0;

            if (($transition['scope'] ?? '') === 'global') {
                $targetMetrics = $globalMetrics[$to] ?? [
                    'max_width' => $metrics['width'],
                    'stack_height' => $metrics['height'],
                ];
                $offset = $this->verticalGlobalStackOffset($id, $to);
                $x = $position['x'] + $position['w'] / 2;
                $y = $position['y']
                    - $gap
                    - (float) $targetMetrics['stack_height']
                    + $offset
                    + $metrics['height'] / 2;
            } elseif ((string) ($transition['from'] ?? '') === $to) {
                $offset = $this->verticalSelfLoopStackOffset($id, $to);
                $x = $position['x'] + $position['w'] / 2;
                $y = $position['y']
                    + $position['h']
                    + $gap
                    + $offset
                    + $metrics['height'] / 2;
            } else {
                continue;
            }

            $persisted = $this->_persistedTransitionGeometry[$id] ?? null;
            if (is_array($persisted)
                && is_numeric($persisted['label_x'] ?? null)
                && is_numeric($persisted['label_y'] ?? null)) {
                $x = (float) $persisted['label_x'];
                $y = (float) $persisted['label_y'];
            }

            $this->_renderedLabelBoxes[] = $this->verticalTransitionLabelBox(
                $x,
                $y,
                $metrics['width'],
                $metrics['height']
            );
        }
    }

    /**
     * Vertical multi-line cartouches use their real center/height. The legacy
     * horizontal collision box is intentionally not reused here.
     *
     * @return array{0:float,1:float}
     */
    private function reserveVerticalTransitionLabel(
        float $labelX,
        float $labelY,
        float $width,
        float $height
    ): array {
        $yOffsets = [0.0, 28.0, -28.0, 56.0, -56.0, 84.0, -84.0];
        $xOffsets = [0.0, 42.0, -42.0, 84.0, -84.0, 126.0, -126.0];
        $candidates = [];
        foreach ($yOffsets as $offsetY) {
            foreach ($xOffsets as $offsetX) {
                $candidates[] = [$offsetX, $offsetY];
            }
        }
        usort(
            $candidates,
            static fn (array $left, array $right): int =>
                hypot($left[0], $left[1]) <=> hypot($right[0], $right[1])
                ?: abs($left[1]) <=> abs($right[1])
                ?: abs($left[0]) <=> abs($right[0])
        );

        foreach ($candidates as [$offsetX, $offsetY]) {
            $candidateX = $labelX + $offsetX;
            $candidateY = max(54.0 + $height / 2, $labelY + $offsetY);
            $box = $this->verticalTransitionLabelBox(
                $candidateX,
                $candidateY,
                $width,
                $height
            );
            if ($this->labelBoxCollides($box)) {
                continue;
            }
            $this->_renderedLabelBoxes[] = $box;
            return [$candidateX, $candidateY];
        }

        $box = $this->verticalTransitionLabelBox(
            $labelX,
            $labelY,
            $width,
            $height
        );
        $this->_renderedLabelBoxes[] = $box;
        return [$labelX, $labelY];
    }

    /**
     * @return array{x1:float,y1:float,x2:float,y2:float}
     */
    private function verticalTransitionLabelBox(
        float $labelX,
        float $labelY,
        float $width,
        float $height
    ): array {
        $padding = 5.0;
        return [
            'x1' => $labelX - $width / 2 - $padding,
            'y1' => $labelY - $height / 2 - $padding,
            'x2' => $labelX + $width / 2 + $padding,
            'y2' => $labelY + $height / 2 + $padding,
        ];
    }

    /**
     * Reserve a readable transition-label box. Routing remains semantic;
     * only the visual label position is nudged when it would collide with an
     * earlier label or a state node.
     *
     * @return array{0:float,1:float}
     */
    private function reserveTransitionLabel(
        float $labelX,
        float $labelY,
        float $width,
        float $height
    ): array {
        if ($this->_layoutDirection === 'vertical') {
            $yOffsets = [0.0];
            for ($step = 1; $step <= 16; ++$step) {
                $delta = $step * 62.0;
                $yOffsets[] = $delta;
                $yOffsets[] = -$delta;
            }
            $xOffsets = [0.0];
            for ($step = 1; $step <= 8; ++$step) {
                $delta = $step * 92.0;
                $xOffsets[] = $delta;
                $xOffsets[] = -$delta;
            }
        } else {
            $yOffsets = [
                0.0, 26.0, -26.0, 52.0, -52.0,
                78.0, -78.0, 104.0, -104.0,
            ];
            $xOffsets = [
                0.0, 34.0, -34.0, 68.0, -68.0, 102.0, -102.0,
            ];
        }
        $candidates = [];
        foreach ($yOffsets as $offsetY) {
            foreach ($xOffsets as $offsetX) {
                $candidates[] = [$offsetX, $offsetY];
            }
        }
        usort(
            $candidates,
            static fn (array $left, array $right): int =>
                hypot($left[0], $left[1]) <=> hypot($right[0], $right[1])
                ?: abs($left[1]) <=> abs($right[1])
                ?: abs($left[0]) <=> abs($right[0])
        );

        foreach ($candidates as [$offsetX, $offsetY]) {
            $candidateX = $labelX + $offsetX;
            if ($this->_renderWidth > 0.0) {
                $candidateX = max(
                    $width / 2 + 8.0,
                    min(
                        $this->_renderWidth - $width / 2 - 8.0,
                        $candidateX
                    )
                );
            }
            $candidateY = max(44.0 + $height / 2, $labelY + $offsetY);
            if ($this->_renderHeight > 0.0) {
                $candidateY = min(
                    $this->_renderHeight - $height / 2 - 8.0,
                    $candidateY
                );
            }
            $box = $this->transitionLabelBox(
                $candidateX,
                $candidateY,
                $width,
                $height
            );

            if ($this->labelBoxCollides($box)) {
                continue;
            }

            $this->_renderedLabelBoxes[] = $box;
            return [$candidateX, $candidateY];
        }

        $candidateX = $labelX;
        if ($this->_renderWidth > 0.0) {
            $candidateX = max(
                $width / 2 + 8.0,
                min(
                    $this->_renderWidth - $width / 2 - 8.0,
                    $candidateX
                )
            );
        }
        $candidateY = max(44.0 + $height / 2, $labelY);
        if ($this->_renderHeight > 0.0) {
            $candidateY = min(
                $this->_renderHeight - $height / 2 - 8.0,
                $candidateY
            );
        }
        $box = $this->transitionLabelBox(
            $candidateX,
            $candidateY,
            $width,
            $height
        );
        $this->_renderedLabelBoxes[] = $box;

        return [$candidateX, $candidateY];
    }

    /**
     * @return array{x1:float,y1:float,x2:float,y2:float}
     */
    private function transitionLabelBox(
        float $labelX,
        float $labelY,
        float $width,
        float $height
    ): array {
        $padding = 5.0;

        return [
            'x1' => $labelX - $width / 2 - $padding,
            'y1' => $labelY - $height / 2 - $padding,
            'x2' => $labelX + $width / 2 + $padding,
            'y2' => $labelY + $height / 2 + $padding,
        ];
    }

    /**
     * @param array{x1:float,y1:float,x2:float,y2:float} $box
     */
    private function labelBoxCollides(array $box): bool
    {
        foreach ($this->_renderedLabelBoxes as $reserved) {
            if ($this->boxesIntersect($box, $reserved)) {
                return true;
            }
        }

        foreach ($this->_renderPositions as $position) {
            $node = [
                'x1' => $position['x'] - 5.0,
                'y1' => $position['y'] - 5.0,
                'x2' => $position['x'] + $position['w'] + 5.0,
                'y2' => $position['y'] + $position['h'] + 5.0,
            ];

            if ($this->boxesIntersect($box, $node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{x1:float,y1:float,x2:float,y2:float} $left
     * @param array{x1:float,y1:float,x2:float,y2:float} $right
     */
    private function boxesIntersect(array $left, array $right): bool
    {
        return !(
            $left['x2'] <= $right['x1']
            || $left['x1'] >= $right['x2']
            || $left['y2'] <= $right['y1']
            || $left['y1'] >= $right['y2']
        );
    }

    /**
     * @param array{
     *   signal:string,
     *   guards:list<string>,
     *   actions:list<string>,
     *   runtime_operations:list<array<string,mixed>>
     * } $transition
     */
    private static function transitionLabel(array $transition): string
    {
        $signal = match ($transition['signal']) {
            '__any__' => '*',
            '__default__' => 'default',
            default => $transition['signal'],
        };

        $guard = $transition['guards'] === []
            ? ''
            : ' [' . implode(' && ', $transition['guards']) . ']';

        $effects = [];
        foreach ($transition['actions'] as $action) {
            $effects[] = self::callLabel($action);
        }
        foreach ($transition['runtime_operations'] as $operation) {
            $op = trim((string) ($operation['op'] ?? ''));
            if ($op === '') {
                continue;
            }
            $name = trim((string) ($operation['name'] ?? ''));
            $effects[] = $op . ($name !== '' ? '(' . $name . ')' : '()');
        }

        return $signal
            . $guard
            . ($effects === [] ? '' : ' / ' . implode('; ', $effects));
    }

    private static function callLabel(string $action): string
    {
        $action = trim($action);
        if ($action === '') {
            return '';
        }
        return str_ends_with($action, ')')
            ? $action
            : $action . '()';
    }

    /**
     * @param array<string,array{x:float,y:float,w:float,h:float,rank:int}> $positions
     * @param array{x:float,y:float,w:float,h:float,rank:int} $position
     */
    private function renderState(
        string $state,
        array $position
    ): string {
        $classes = 'fsm-node';
        if ($state === $this->_currentState) {
            $classes .= ' current';
        }

        $labelY = $position['y'] + 32;
        $stateLabel = $this->_stateLabels[$state] ?? $state;
        $link = $this->_stateLinks[$state] ?? null;
        $svg = is_string($link)
            ? '<a class="fsm-node-link" href="' . self::h($link) . '">'
            : '';
        $svg .= '<g class="' . $classes . '" data-state="'
            . self::h($state)
            . '" data-x="' . self::n($position['x'])
            . '" data-y="' . self::n($position['y'])
            . '" data-w="' . self::n($position['w'])
            . '" data-h="' . self::n($position['h'])
            . '" data-layout-draggable="'
            . ((($this->_layoutPersistence['writable'] ?? false) === true)
                ? '1'
                : '0')
            . '">';
        $svg .= '<rect x="' . self::n($position['x'])
            . '" y="' . self::n($position['y'])
            . '" width="' . self::n($position['w'])
            . '" height="' . self::n($position['h'])
            . '" rx="8" />';
        $svg .= '<text class="fsm-state-label" x="'
            . self::n($position['x'] + $position['w'] / 2)
            . '" y="' . self::n($labelY)
            . '">' . self::h($stateLabel) . '</text>';

        if ($state === $this->_currentState) {
            $svg .= '<text class="fsm-node-tag" x="'
                . self::n($position['x'] + $position['w'] / 2)
                . '" y="' . self::n($labelY + 20)
                . '">current</text>';
        }

        $annotations = $this->_stateAnnotations[$state] ?? [];
        if ($annotations !== []) {
            $svg .= '<text class="fsm-state-annotation" x="'
                . self::n($position['x'] + $position['w'] / 2)
                . '" y="' . self::n($position['y'] + $position['h'] - 10)
                . '">'
                . self::h(
                    'entry / '
                    . implode(
                        '; ',
                        array_map(
                            self::callLabel(...),
                            $annotations
                        )
                    )
                )
                . '</text>';
        }

        $svg .= '</g>';
        if (is_string($link)) {
            $svg .= '</a>';
        }
        return $svg;
    }

    /**
     * @param array<string,array{x:float,y:float,w:float,h:float,rank:int}> $positions
     */
    private function renderInitialMarker(array $positions): string
    {
        if ($this->_initialState === ''
            || !isset($positions[$this->_initialState])) {
            return '';
        }

        $target = $positions[$this->_initialState];
        $persisted = $this->_persistedMarkerGeometry['initial'] ?? null;
        if (is_array($persisted)) {
            $cx = (float) $persisted['x'];
            $cy = (float) $persisted['y'];
        } elseif ($this->_layoutDirection === 'vertical') {
            $cx = $target['x'] + $target['w'] / 2;
            $cy = max(28.0, $target['y'] - 54.0);
        } else {
            $cx = max(28.0, $target['x'] - 54.0);
            $cy = $target['y'] + $target['h'] / 2;
        }

        $this->_renderedMarkerGeometry['initial'] = [
            'x' => $cx,
            'y' => $cy,
        ];

        $path = $this->initialMarkerPath($cx, $cy, $target);
        $writable = (($this->_layoutPersistence['writable'] ?? false) === true);

        return '<g class="fsm-initial-marker" aria-label="initial"'
            . ' data-marker-id="initial"'
            . ' data-anchor-state="' . self::h($this->_initialState) . '"'
            . ' data-marker-x="' . self::n($cx) . '"'
            . ' data-marker-y="' . self::n($cy) . '"'
            . ' data-layout-marker-draggable="' . ($writable ? '1' : '0') . '">'
            . '<circle cx="' . self::n($cx)
            . '" cy="' . self::n($cy)
            . '" r="9" />'
            . '<path class="fsm-edge" d="' . self::h($path)
            . '" marker-end="url(#fsm-arrow-unspecified)" />'
            . '</g>';
    }

    /**
     * Keep the initial pseudo-state presentation point independent from the
     * canonical initial_state while always routing its arrow to the current
     * initial-state rectangle boundary.
     *
     * @param array{x:float,y:float,w:float,h:float,rank:int} $target
     */
    private function initialMarkerPath(
        float $cx,
        float $cy,
        array $target
    ): string {
        $tx = $target['x'] + $target['w'] / 2;
        $ty = $target['y'] + $target['h'] / 2;
        $dx = $tx - $cx;
        $dy = $ty - $cy;
        $length = hypot($dx, $dy);
        if ($length < 0.001) {
            $dx = 0.0;
            $dy = 1.0;
            $length = 1.0;
        }

        $startRadius = 10.0;
        $sx = $cx + ($dx / $length) * $startRadius;
        $sy = $cy + ($dy / $length) * $startRadius;

        $halfW = max(1.0, $target['w'] / 2);
        $halfH = max(1.0, $target['h'] / 2);
        $scaleX = abs($dx) < 0.001 ? INF : $halfW / abs($dx);
        $scaleY = abs($dy) < 0.001 ? INF : $halfH / abs($dy);
        $scale = min($scaleX, $scaleY);
        if (!is_finite($scale)) {
            $scale = 0.0;
        }

        $ex = $tx - $dx * $scale;
        $ey = $ty - $dy * $scale;

        return 'M' . self::n($sx)
            . ' ' . self::n($sy)
            . ' L' . self::n($ex)
            . ' ' . self::n($ey);
    }

    /**
     * @param array<string,array{x:float,y:float,w:float,h:float,rank:int}> $positions
     */
    private function renderFinalMarker(array $positions): string
    {
        if ($this->_finalState === ''
            || !isset($positions[$this->_finalState])) {
            return '';
        }

        $state = $positions[$this->_finalState];
        $cx = $state['x'] + $state['w'] - 17.0;
        $cy = $state['y'] + 17.0;

        return '<g class="fsm-final-marker" aria-label="final"'
            . ' data-anchor-state="' . self::h($this->_finalState) . '">'
            . '<circle cx="' . self::n($cx)
            . '" cy="' . self::n($cy)
            . '" r="9" />'
            . '<circle cx="' . self::n($cx)
            . '" cy="' . self::n($cy)
            . '" r="4" />'
            . '</g>';
    }

    private function hasGlobalSource(): bool
    {
        foreach ($this->_transitions as $transition) {
            if ($transition['from'] === '*') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,array{x:float,y:float,w:float,h:float,rank:int}> $positions
     * @return array{x:float,y:float}
     */
    private function globalSourcePoint(array $positions): array
    {
        $minY = 105.0;
        foreach ($positions as $position) {
            $minY = min($minY, $position['y']);
        }

        return [
            'x' => 48.0,
            'y' => max(92.0, $minY - 58.0),
        ];
    }

    /**
     * @param array<string,array{x:float,y:float,w:float,h:float,rank:int}> $positions
     */
    private function renderGlobalSource(array $positions): string
    {
        if (!$this->hasGlobalSource()) {
            return '';
        }

        $point = $this->globalSourcePoint($positions);
        $maxX = $point['x'] + 29.0;
        foreach ($positions as $position) {
            $maxX = max(
                $maxX,
                $position['x'] + $position['w'] / 2
            );
        }

        return '<g class="fsm-global-source fsm-nmi-source">'
            . '<rect x="' . self::n($point['x'] - 29)
            . '" y="' . self::n($point['y'] - 15)
            . '" width="58" height="30" rx="6" />'
            . '<text x="' . self::n($point['x'])
            . '" y="' . self::n($point['y'] + 5)
            . '">NMI</text>'
            . '<path class="fsm-global-bus" d="M'
            . self::n($point['x'] + 29)
            . ' ' . self::n($point['y'])
            . ' H' . self::n($maxX) . '" />'
            . '<title>Interruption non masquable hors ensemble des états</title>'
            . '</g>';
    }

    private function renderLegend(float $width, float $height): string
    {
        $items = [];
        if ($this->hasGlobalSource()) {
            $items[] = 'NMI = interruption non masquable';
        }

        foreach ($this->_transitions as $transition) {
            if ($transition['signal'] === '__any__') {
                $items[] = '* signal = tout signal';
                break;
            }
        }

        foreach ($this->_transitions as $transition) {
            if ($transition['signal'] === '__default__') {
                $items[] = 'default = transition de repli';
                break;
            }
        }

        if ($this->_fallbackEffects !== []) {
            $items[] = 'fallback effect: '
                . implode(
                    '; ',
                    array_map(
                        self::callLabel(...),
                        $this->_fallbackEffects
                    )
                );
        }

        if ($items === []) {
            return '';
        }

        $y = $height - 34.0;
        return '<g class="fsm-legend">'
            . '<text x="34" y="' . self::n($y)
            . '">' . self::h(implode(' · ', array_unique($items)))
            . '</text>'
            . '</g>';
    }

    private function renderRuntimeFacts(): string
    {
        if ($this->_memory === []) {
            return '';
        }

        $items = [];
        foreach ($this->_memory as $key => $value) {
            if (count($items) >= 6) {
                break;
            }

            if (is_array($value)) {
                $value = 'Array';
            } elseif (is_object($value)) {
                $value = 'Object';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = 'null';
            } else {
                $value = strip_tags((string) $value);
            }

            $items[] = '<span><strong>'
                . self::h((string) $key)
                . '</strong>='
                . self::h(self::shorten((string) $value, 48))
                . '</span>';
        }

        return '<div class="fsm-runtime-memory">'
            . '<strong>Runtime memory</strong> '
            . implode(' ', $items)
            . '</div>';
    }

    /**
     * Development-only right-button drag interaction.
     *
     * The browser keeps the live page intact. Right-button drag can move a
     * state, signal card or initial pseudo-state marker. On release, state
     * coordinates and the exact
     * transition presentation geometry currently visible in the SVG are saved
     * asynchronously. No page reload is performed, so surrounding UI state
     * (including native menu collapse) is untouched.
     */
    private static function layoutInteractionScript(): string
    {
        return <<<'HTML'
<script>
(() => {
  const script = document.currentScript;
  const card = script && script.previousElementSibling;
  if (!(card instanceof HTMLElement)
      || card.dataset.opusFsmLayoutWritable !== '1') {
    return;
  }
  const svg = card.querySelector('svg.fsm-diagram');
  if (!(svg instanceof SVGSVGElement)) return;

  const states = new Map();
  svg.querySelectorAll('.fsm-node[data-state]').forEach((node) => {
    const id = node.dataset.state || '';
    if (id === '') return;
    states.set(id, {
      node,
      x:Number(node.dataset.x || 0),
      y:Number(node.dataset.y || 0),
      w:Number(node.dataset.w || 0),
      h:Number(node.dataset.h || 0),
      dx:0,
      dy:0,
    });
  });

  const signals = new Map();
  svg.querySelectorAll('.fsm-transition[data-transition-id]').forEach((group) => {
    const id = group.dataset.transitionId || '';
    const node = group.querySelector(
      '.fsm-signal-card[data-layout-signal-draggable="1"]'
    );
    if (id === '' || !(node instanceof SVGGElement)) return;
    signals.set(id, {
      node,
      group,
      x:Number(node.dataset.signalX || 0),
      y:Number(node.dataset.signalY || 0),
      w:Number(node.dataset.signalW || 0),
      h:Number(node.dataset.signalH || 0),
      dx:0,
      dy:0,
    });
  });

  const markers = new Map();
  svg.querySelectorAll(
    '.fsm-initial-marker[data-marker-id][data-layout-marker-draggable="1"]'
  ).forEach((node) => {
    const id = node.dataset.markerId || '';
    if (id === '') return;
    markers.set(id, {
      node,
      x:Number(node.dataset.markerX || 0),
      y:Number(node.dataset.markerY || 0),
      dx:0,
      dy:0,
      r:9,
    });
  });

  const pointFor = (event) => {
    const point = svg.createSVGPoint();
    point.x = event.clientX;
    point.y = event.clientY;
    const matrix = svg.getScreenCTM();
    return matrix ? point.matrixTransform(matrix.inverse()) : point;
  };

  const boxFor = (id) => {
    const item = states.get(id);
    if (!item) return null;
    return {x:item.x + item.dx, y:item.y + item.dy, w:item.w, h:item.h};
  };

  const initialMarkerPath = (marker, target) => {
    if (!marker || !target) return '';
    const cx = marker.x + marker.dx;
    const cy = marker.y + marker.dy;
    const tx = target.x + target.w / 2;
    const ty = target.y + target.h / 2;
    let dx = tx - cx;
    let dy = ty - cy;
    let length = Math.hypot(dx, dy);
    if (!Number.isFinite(length) || length < 0.001) {
      dx = 0;
      dy = 1;
      length = 1;
    }
    const startRadius = marker.r + 1;
    const sx = cx + (dx / length) * startRadius;
    const sy = cy + (dy / length) * startRadius;
    const halfW = Math.max(1, target.w / 2);
    const halfH = Math.max(1, target.h / 2);
    const scaleX = Math.abs(dx) < 0.001 ? Number.POSITIVE_INFINITY : halfW / Math.abs(dx);
    const scaleY = Math.abs(dy) < 0.001 ? Number.POSITIVE_INFINITY : halfH / Math.abs(dy);
    let scale = Math.min(scaleX, scaleY);
    if (!Number.isFinite(scale)) scale = 0;
    const ex = tx - dx * scale;
    const ey = ty - dy * scale;
    return `M${sx} ${sy} L${ex} ${ey}`;
  };

  const updateInitialMarker = () => {
    const marker = markers.get('initial');
    if (!marker) return;
    const targetId = marker.node.dataset.anchorState || '';
    const target = boxFor(targetId);
    if (!target) return;
    const cx = marker.x + marker.dx;
    const cy = marker.y + marker.dy;
    const circle = marker.node.querySelector('circle');
    const edge = marker.node.querySelector('path.fsm-edge');
    if (circle instanceof SVGCircleElement) {
      circle.setAttribute('cx', String(cx));
      circle.setAttribute('cy', String(cy));
    }
    if (edge instanceof SVGPathElement) {
      edge.setAttribute('d', initialMarkerPath(marker, target));
    }
  };

  const anchorOffset = (group) => {
    const id = group.dataset.anchorState || '';
    const item = states.get(id);
    return item ? {x:item.dx, y:item.dy} : {x:0, y:0};
  };

  const signalLocalCenter = (group) => {
    const id = group.dataset.transitionId || '';
    const item = signals.get(id);
    if (!item) return null;
    return {x:item.x + item.dx, y:item.y + item.dy};
  };

  const signalAbsoluteCenter = (group) => {
    const center = signalLocalCenter(group);
    if (!center) return null;
    const offset = anchorOffset(group);
    return {x:center.x + offset.x, y:center.y + offset.y};
  };

  const pathFor = (fromId, toId) => {
    const from = boxFor(fromId);
    const to = boxFor(toId);
    if (!from || !to) return '';
    const fromCx = from.x + from.w / 2;
    const fromCy = from.y + from.h / 2;
    const toCx = to.x + to.w / 2;
    const toCy = to.y + to.h / 2;
    const dx = toCx - fromCx;
    const dy = toCy - fromCy;
    if (Math.abs(dy) >= Math.abs(dx)) {
      const down = dy >= 0;
      const x1 = fromCx;
      const y1 = down ? from.y + from.h : from.y;
      const x2 = toCx;
      const y2 = down ? to.y : to.y + to.h;
      const midY = (y1 + y2) / 2;
      return `M${x1} ${y1} C${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}`;
    }
    const right = dx >= 0;
    const x1 = right ? from.x + from.w : from.x;
    const y1 = fromCy;
    const x2 = right ? to.x : to.x + to.w;
    const y2 = toCy;
    const midX = (x1 + x2) / 2;
    return `M${x1} ${y1} C${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}`;
  };

  const pointOnBoundary = (point, box) => {
    if (!point || !box) return false;
    const tolerance = 3;
    const left = box.x;
    const top = box.y;
    const right = box.x + box.w;
    const bottom = box.y + box.h;
    const insideX = point.x >= left - tolerance && point.x <= right + tolerance;
    const insideY = point.y >= top - tolerance && point.y <= bottom + tolerance;
    if (!insideX || !insideY) return false;
    return Math.abs(point.x - left) <= tolerance
      || Math.abs(point.x - right) <= tolerance
      || Math.abs(point.y - top) <= tolerance
      || Math.abs(point.y - bottom) <= tolerance;
  };

  const edgeAnchored = (edge, fromId, toId) => {
    const from = boxFor(fromId);
    const to = boxFor(toId);
    if (!(edge instanceof SVGPathElement) || !from || !to) return false;
    try {
      const length = edge.getTotalLength();
      if (!Number.isFinite(length) || length <= 0) return false;
      return pointOnBoundary(edge.getPointAtLength(0), from)
        && pointOnBoundary(edge.getPointAtLength(length), to);
    } catch (_) {
      return false;
    }
  };

  const fallbackLeaderAnchor = (group) => {
    const anchorId = group.dataset.anchorState || group.dataset.toState || '';
    const item = states.get(anchorId);
    if (!item) return null;
    const scope = group.dataset.transitionScope || '';
    const from = group.dataset.fromState || '';
    const to = group.dataset.toState || '';
    if (scope === 'global') {
      return {x:item.x + item.w / 2, y:item.y};
    }
    if (from !== '' && from === to) {
      return {x:item.x + item.w / 2, y:item.y + item.h};
    }
    return {x:item.x + item.w / 2, y:item.y + item.h / 2};
  };

  const updateLabelLeader = (group, edge) => {
    const center = signalLocalCenter(group);
    if (!center) return;
    let leader = group.querySelector('path.fsm-label-leader');
    let anchor = null;
    if (edge instanceof SVGPathElement) {
      try {
        const length = edge.getTotalLength();
        if (Number.isFinite(length) && length > 0) {
          anchor = edge.getPointAtLength(length / 2);
        }
      } catch (_) {
        anchor = null;
      }
    }
    if (!anchor) anchor = fallbackLeaderAnchor(group);
    if (!anchor) {
      if (leader instanceof SVGPathElement) leader.remove();
      return;
    }
    const distance = Math.hypot(center.x - anchor.x, center.y - anchor.y);
    if (distance < 18) {
      if (leader instanceof SVGPathElement) leader.remove();
      return;
    }
    if (!(leader instanceof SVGPathElement)) {
      leader = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      leader.setAttribute('class', 'fsm-label-leader');
      const signal = group.querySelector('.fsm-signal-card');
      if (signal && signal.parentNode === group) {
        group.insertBefore(leader, signal);
      } else {
        group.appendChild(leader);
      }
    }
    leader.setAttribute(
      'd',
      `M${anchor.x} ${anchor.y} L${center.x} ${center.y}`
    );
  };

  const repairLocalTransition = (group) => {
    const from = group.dataset.fromState || '';
    const to = group.dataset.toState || '';
    const scope = group.dataset.transitionScope || '';
    if (scope === 'global' || from === '' || to === '' || from === to
        || !states.has(from) || !states.has(to)) {
      updateLabelLeader(group, group.querySelector('path.fsm-edge'));
      return;
    }
    const edge = group.querySelector('path.fsm-edge');
    if (!(edge instanceof SVGPathElement)) return;
    if (!edgeAnchored(edge, from, to)) {
      const d = pathFor(from, to);
      if (d !== '') edge.setAttribute('d', d);
    }
    updateLabelLeader(group, edge);
  };

  const updateGeometry = (state) => {
    const item = states.get(state);
    if (!item) return;
    svg.querySelectorAll('.fsm-transition[data-from-state][data-to-state]')
      .forEach((group) => {
        const from = group.dataset.fromState || '';
        const to = group.dataset.toState || '';
        const anchor = group.dataset.anchorState || '';
        if (anchor === state) {
          group.setAttribute('transform', `translate(${item.dx} ${item.dy})`);
          updateLabelLeader(group, group.querySelector('path.fsm-edge'));
          return;
        }
        if ((from !== state && to !== state)
            || !states.has(from)
            || !states.has(to)) {
          return;
        }
        const edge = group.querySelector('path.fsm-edge');
        if (edge instanceof SVGPathElement) {
          const d = pathFor(from, to);
          if (d !== '') edge.setAttribute('d', d);
          updateLabelLeader(group, edge);
        }
      });
    svg.querySelectorAll(`[data-anchor-state="${CSS.escape(state)}"]`)
      .forEach((element) => {
        if (element.classList.contains('fsm-transition')) return;
        if (element.classList.contains('fsm-initial-marker')) {
          updateInitialMarker();
          return;
        }
        element.setAttribute('transform', `translate(${item.dx} ${item.dy})`);
      });
  };

  const geometrySnapshot = () => {
    const viewBox = svg.viewBox.baseVal;
    const transitions = {};
    svg.querySelectorAll('.fsm-transition[data-transition-id]')
      .forEach((group) => {
        const id = group.dataset.transitionId || '';
        if (id === '') return;
        repairLocalTransition(group);
        const signal = signals.get(id);
        if (!signal) return;
        const center = signalAbsoluteCenter(group);
        if (!center) return;
        const edge = group.querySelector('path.fsm-edge');
        const leader = group.querySelector('path.fsm-label-leader');
        transitions[id] = {
          path:edge instanceof SVGPathElement ? (edge.getAttribute('d') || '') : '',
          label_x:center.x,
          label_y:center.y,
          leader_path:leader instanceof SVGPathElement
            ? (leader.getAttribute('d') || '')
            : '',
        };
      });
    const markerGeometry = {};
    markers.forEach((item, id) => {
      markerGeometry[id] = {
        x:item.x + item.dx,
        y:item.y + item.dy,
      };
    });
    return {
      canvas:{width:viewBox.width, height:viewBox.height},
      transitions,
      markers:markerGeometry,
    };
  };

  svg.querySelectorAll('.fsm-transition[data-transition-id]')
    .forEach((group) => repairLocalTransition(group));
  updateInitialMarker();

  const rotateCsrfFromResponse = (html) => {
    const documentCopy = new DOMParser().parseFromString(html, 'text/html');
    const key = card.dataset.opusFsmLayoutKey || '';
    let nextCard = null;
    documentCopy.querySelectorAll('.fsm-diagram-card[data-opus-fsm-layout-key]')
      .forEach((candidate) => {
        if (!nextCard && candidate.dataset.opusFsmLayoutKey === key) {
          nextCard = candidate;
        }
      });
    if (nextCard instanceof HTMLElement) {
      const token = nextCard.dataset.opusFsmLayoutCsrf || '';
      if (token !== '') card.dataset.opusFsmLayoutCsrf = token;
    }
  };

  const persist = async (kind, id) => {
    const body = new URLSearchParams();
    body.set('owasys_action', 'persist-fsm-layout');
    const action = kind === 'state'
      ? 'save-state'
      : (kind === 'marker' ? 'save-marker' : 'save-signal');
    body.set('opus_fsm_layout_action', action);
    body.set('opus_fsm_layout_key', card.dataset.opusFsmLayoutKey || '');
    if (kind === 'state') {
      const item = states.get(id);
      if (!item) return;
      body.set('opus_fsm_layout_state', id);
      body.set('opus_fsm_layout_x', String(item.x + item.dx));
      body.set('opus_fsm_layout_y', String(item.y + item.dy));
    } else if (kind === 'marker') {
      if (!markers.has(id)) return;
      body.set('opus_fsm_layout_marker', id);
    }
    body.set('opus_fsm_layout_geometry', JSON.stringify(geometrySnapshot()));
    body.set('csrf_token', card.dataset.opusFsmLayoutCsrf || '');
    const response = await fetch(window.location.href, {
      method:'POST',
      credentials:'same-origin',
      headers:{
        'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8',
        'X-Requested-With':'OPUS-FSM-Layout',
      },
      body:body.toString(),
    });
    const responseHtml = await response.text();
    if (!response.ok) {
      throw new Error(`OPUS_FSM_DIAGRAM_LAYOUT_SAVE_FAILED:${response.status}`);
    }
    rotateCsrfFromResponse(responseHtml);
  };

  let drag = null;

  const draggableTarget = (target) => {
    if (!(target instanceof Element)) return null;
    const marker = target.closest(
      '.fsm-initial-marker[data-layout-marker-draggable="1"]'
    );
    if (marker instanceof SVGGElement) return {kind:'marker', node:marker};
    const signal = target.closest(
      '.fsm-signal-card[data-layout-signal-draggable="1"]'
    );
    if (signal instanceof SVGGElement) return {kind:'signal', node:signal};
    const state = target.closest('.fsm-node[data-layout-draggable="1"]');
    if (state instanceof SVGGElement) return {kind:'state', node:state};
    return null;
  };

  svg.addEventListener('contextmenu', (event) => {
    if (draggableTarget(event.target)) event.preventDefault();
  });

  svg.addEventListener('auxclick', (event) => {
    if (event.button === 2 && draggableTarget(event.target)) {
      event.preventDefault();
    }
  });

  svg.addEventListener('pointerdown', (event) => {
    if (event.button !== 2) return;
    const target = draggableTarget(event.target);
    if (!target) return;
    event.preventDefault();
    const start = pointFor(event);
    if (target.kind === 'state') {
      const state = target.node.dataset.state || '';
      const item = states.get(state);
      if (!item) return;
      drag = {
        kind:'state',
        id:state,
        node:target.node,
        pointerId:event.pointerId,
        startX:start.x,
        startY:start.y,
        baseDx:item.dx,
        baseDy:item.dy,
      };
    } else if (target.kind === 'marker') {
      const id = target.node.dataset.markerId || '';
      const item = markers.get(id);
      if (!item) return;
      drag = {
        kind:'marker',
        id,
        node:target.node,
        pointerId:event.pointerId,
        startX:start.x,
        startY:start.y,
        baseDx:item.dx,
        baseDy:item.dy,
      };
    } else {
      const group = target.node.closest('.fsm-transition[data-transition-id]');
      const id = group instanceof SVGGElement
        ? (group.dataset.transitionId || '')
        : '';
      const item = signals.get(id);
      if (!item) return;
      drag = {
        kind:'signal',
        id,
        node:target.node,
        pointerId:event.pointerId,
        startX:start.x,
        startY:start.y,
        baseDx:item.dx,
        baseDy:item.dy,
      };
    }
    svg.setPointerCapture(event.pointerId);
    card.classList.add('is-layout-dragging');
    target.node.classList.add('is-layout-dragging');
  });

  svg.addEventListener('pointermove', (event) => {
    if (!drag || event.pointerId !== drag.pointerId) return;
    event.preventDefault();
    const point = pointFor(event);
    const viewBox = svg.viewBox.baseVal;
    if (drag.kind === 'state') {
      const item = states.get(drag.id);
      if (!item) return;
      let x = item.x + drag.baseDx + (point.x - drag.startX);
      let y = item.y + drag.baseDy + (point.y - drag.startY);
      x = Math.max(8, Math.min(x, Math.max(8, viewBox.width - item.w - 8)));
      y = Math.max(70, Math.min(y, Math.max(70, viewBox.height - item.h - 8)));
      item.dx = x - item.x;
      item.dy = y - item.y;
      item.node.setAttribute('transform', `translate(${item.dx} ${item.dy})`);
      updateGeometry(drag.id);
      return;
    }

    if (drag.kind === 'marker') {
      const item = markers.get(drag.id);
      if (!item) return;
      let x = item.x + drag.baseDx + (point.x - drag.startX);
      let y = item.y + drag.baseDy + (point.y - drag.startY);
      x = Math.max(item.r + 8, Math.min(
        x,
        Math.max(item.r + 8, viewBox.width - item.r - 8)
      ));
      y = Math.max(item.r + 70, Math.min(
        y,
        Math.max(item.r + 70, viewBox.height - item.r - 8)
      ));
      item.dx = x - item.x;
      item.dy = y - item.y;
      updateInitialMarker();
      return;
    }

    const item = signals.get(drag.id);
    if (!item) return;
    const offset = anchorOffset(item.group);
    let x = item.x + drag.baseDx + (point.x - drag.startX) + offset.x;
    let y = item.y + drag.baseDy + (point.y - drag.startY) + offset.y;
    x = Math.max(
      item.w / 2 + 8,
      Math.min(x, Math.max(item.w / 2 + 8, viewBox.width - item.w / 2 - 8))
    );
    y = Math.max(
      item.h / 2 + 70,
      Math.min(y, Math.max(item.h / 2 + 70, viewBox.height - item.h / 2 - 8))
    );
    item.dx = x - offset.x - item.x;
    item.dy = y - offset.y - item.y;
    item.node.setAttribute('transform', `translate(${item.dx} ${item.dy})`);
    updateLabelLeader(item.group, item.group.querySelector('path.fsm-edge'));
  });

  const finish = async (event) => {
    if (!drag || event.pointerId !== drag.pointerId) return;
    const completed = drag;
    drag = null;
    card.classList.remove('is-layout-dragging');
    completed.node.classList.remove('is-layout-dragging');
    try {
      await persist(completed.kind, completed.id);
      card.dataset.opusFsmLayoutSaveState = 'saved';
    } catch (error) {
      card.dataset.opusFsmLayoutSaveState = 'error';
      console.error(error);
    }
  };

  svg.addEventListener('pointerup', finish);
  svg.addEventListener('pointercancel', finish);
})();
</script>
HTML;
    }

    private static function svgDefinitions(): string
    {
        return <<<'SVG'
<defs>
  <marker id="fsm-arrow-user" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
    <path d="M0,0 L0,6 L9,3 z" class="fsm-arrow-head signal-origin-user" />
  </marker>
  <marker id="fsm-arrow-automatic" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
    <path d="M0,0 L0,6 L9,3 z" class="fsm-arrow-head signal-origin-automatic" />
  </marker>
  <marker id="fsm-arrow-unspecified" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
    <path d="M0,0 L0,6 L9,3 z" class="fsm-arrow-head signal-origin-unspecified" />
  </marker>
  <style>
    .fsm-diagram { width:auto; max-width:none; height:auto; overflow:visible; font-family:"Segoe UI",Arial,sans-serif; }
    .fsm-diagram[data-opus-fsm-layout="vertical"] { width:auto; max-width:100%; height:auto; }
    .fsm-title { fill:var(--opus-fsm-text,#e8f5ff); font-size:19px; font-weight:800; }
    .fsm-subtitle { fill:var(--opus-fsm-muted,#9fb4cf); font-size:12px; }
    .fsm-node rect { fill:var(--opus-fsm-node-bg,#101c2f); stroke:var(--opus-fsm-node-border,#6b829e); stroke-width:1.5; }
    .fsm-node.current rect { fill:var(--opus-fsm-current-bg,#17365d); stroke:var(--opus-fsm-current-border,#6ce3ff); stroke-width:3; }
    .fsm-node[data-layout-draggable="1"] { cursor:move; touch-action:none; }
    .fsm-node[data-layout-draggable="1"].is-layout-dragging rect { stroke:var(--opus-fsm-focus,#fbbf24); stroke-width:3; }
    .fsm-signal-card[data-layout-signal-draggable="1"] { cursor:move; touch-action:none; }
    .fsm-signal-card[data-layout-signal-draggable="1"].is-layout-dragging .fsm-edge-label-bg { stroke:var(--opus-fsm-focus,#fbbf24); stroke-width:2.4; }
    .fsm-initial-marker[data-layout-marker-draggable="1"] { cursor:move; touch-action:none; }
    .fsm-initial-marker[data-layout-marker-draggable="1"].is-layout-dragging circle { stroke:var(--opus-fsm-focus,#fbbf24); stroke-width:3; }
    .fsm-node-link { cursor:pointer; text-decoration:none; }
    .fsm-node-link:hover .fsm-node rect,
    .fsm-node-link:focus .fsm-node rect { stroke:var(--opus-fsm-focus,#fbbf24); stroke-width:3; }
    .fsm-signal-link { cursor:pointer; text-decoration:none; outline:none; }
    .fsm-transition.signal-origin-user { --opus-fsm-transition-color:var(--opus-fsm-signal-user,#6ce3ff); }
    .fsm-transition.signal-origin-automatic { --opus-fsm-transition-color:var(--opus-fsm-signal-automatic,#fbbf24); }
    .fsm-transition.signal-origin-unspecified { --opus-fsm-transition-color:var(--opus-fsm-edge,#7da4c8); }
    .fsm-transition.actionable .fsm-edge { stroke-width:2.05; }
    .fsm-signal-link .fsm-edge-label { text-decoration:none; font-weight:800; }
    .fsm-signal-post-object { overflow:visible; }
    .fsm-signal-post-form { width:100%; height:100%; margin:0; padding:0; }
    .fsm-signal-post-submit { display:block; width:100%; height:100%; margin:0; padding:0; border:0; background:transparent; color:transparent; cursor:pointer; opacity:0; }
    .fsm-state-label { fill:var(--opus-fsm-state-text,#f6f8ff); font-size:13px; font-weight:800; text-anchor:middle; }
    .fsm-node-tag { fill:var(--opus-fsm-signal,#6ce3ff); font-size:10px; font-weight:800; text-anchor:middle; }
    .fsm-state-annotation { fill:var(--opus-fsm-muted,#b8c5de); font-size:9px; text-anchor:middle; }
    .fsm-edge { fill:none; stroke:var(--opus-fsm-transition-color,var(--opus-fsm-edge,#7da4c8)); stroke-width:1.7; }
    .fsm-label-leader { fill:none; stroke:var(--opus-fsm-transition-color,var(--opus-fsm-edge,#7da4c8)); stroke-width:.9; stroke-dasharray:3 3; opacity:.78; }
    .fsm-transition.actionable .fsm-label-leader { opacity:.95; }
    .fsm-arrow-head.signal-origin-user { fill:var(--opus-fsm-signal-user,#6ce3ff); }
    .fsm-arrow-head.signal-origin-automatic { fill:var(--opus-fsm-signal-automatic,#fbbf24); }
    .fsm-arrow-head.signal-origin-unspecified { fill:var(--opus-fsm-edge,#7da4c8); }
    .fsm-transition.wildcard .fsm-edge,
    .fsm-transition.fallback .fsm-edge { stroke-dasharray:6 5; }
    .fsm-transition.return-edge .fsm-edge { stroke-dasharray:2 2; }
    .fsm-transition.self-loop .fsm-edge { stroke-width:2; }
    .fsm-edge-label { fill:var(--opus-fsm-transition-color,var(--opus-fsm-label,#dbeafe)); font-size:11px; font-weight:650; text-anchor:middle; paint-order:stroke; stroke:var(--opus-fsm-label-halo,#07111f); stroke-width:4px; stroke-linejoin:round; }
    .fsm-edge-signal { font-weight:850; }
    .fsm-edge-guard { fill:var(--opus-fsm-guard,#c4b5fd); font-size:10px; font-weight:700; }
    .fsm-edge-effect { fill:var(--opus-fsm-effect,#86efac); font-size:10px; font-weight:650; }
    .fsm-edge-scope { fill:var(--opus-fsm-muted,#9fb4cf); font-size:8px; font-weight:800; text-anchor:end; text-transform:uppercase; }
    .fsm-transition.global-scope .fsm-edge { stroke-dasharray:4 3; opacity:.86; }
    .fsm-initial-marker circle { fill:var(--opus-fsm-marker,#f6f8ff); stroke:var(--opus-fsm-marker,#f6f8ff); }
    .fsm-final-marker circle:first-child { fill:none; stroke:var(--opus-fsm-marker,#f6f8ff); stroke-width:2; }
    .fsm-final-marker circle:last-child { fill:var(--opus-fsm-marker,#f6f8ff); stroke:none; }
    .fsm-global-source rect { fill:var(--opus-fsm-nmi-bg,#172033); stroke:var(--opus-fsm-nmi,#fbbf24); stroke-width:1.5; stroke-dasharray:5 4; }
    .fsm-global-source text { fill:var(--opus-fsm-nmi,#fbbf24); font-size:16px; font-weight:900; text-anchor:middle; }
    .fsm-global-bus { fill:none; stroke:var(--opus-fsm-nmi,#fbbf24); stroke-width:1.2; stroke-dasharray:5 4; }
    .fsm-legend text { fill:var(--opus-fsm-muted,#9fb4cf); font-size:10px; }
  </style>
</defs>
SVG;
    }

    private static function signalType(string $type): string
    {
        $type = strtolower(trim($type));
        return in_array(
            $type,
            ['navigation', 'command', 'outcome', 'system'],
            true
        ) ? $type : 'system';
    }

    private static function signalOrigin(string $origin): string
    {
        $origin = strtolower(trim($origin));
        if ($origin === '') {
            return 'unspecified';
        }
        if (!in_array($origin, ['user', 'automatic'], true)) {
            throw new \InvalidArgumentException(
                'OPUS_FSM_DIAGRAM_SIGNAL_ORIGIN_INVALID:' . $origin
            );
        }
        return $origin;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? [] : [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return array_values($result);
    }

    private static function shorten(string $text, int $max): string
    {
        if ($max <= 0) {
            return '';
        }

        if (function_exists('mb_strlen')
            && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max
                ? mb_substr($text, 0, $max - 1, 'UTF-8') . '…'
                : $text;
        }

        return strlen($text) > $max
            ? substr($text, 0, $max - 1) . '…'
            : $text;
    }

    private static function h(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    private static function n(float|int $value): string
    {
        return rtrim(
            rtrim(
                number_format((float) $value, 2, '.', ''),
                '0'
            ),
            '.'
        );
    }
}
