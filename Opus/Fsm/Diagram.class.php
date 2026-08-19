<?php
declare(strict_types=1);

/**
 * Semantic SVG renderer for OPUS finite-state machines.
 *
 * The renderer is intentionally dependency-free:
 * - no GraphViz;
 * - no external process execution;
 * - no JavaScript;
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
        return $diagram->renderHtml();
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

        return '<div class="fsm-diagram-card">'
            . '<div class="fsm-diagram-toolbar">'
            . '<strong>' . self::h($this->_title) . '</strong>'
            . '<span>FSM · SVG natif · transitions sémantiques</span>'
            . '</div>'
            . $this->renderSvg()
            . $runtime
            . '</div>';
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
            return $this->layoutVertical($states);
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

        return [
            'positions' => $positions,
            'width' => $width,
            'height' => $height,
        ];
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
                $transition
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
                $transition
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
                $transition
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
                $transition
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
                $transition
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
                $transition
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
            $transition
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
                $transition
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
                $transition
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
                $transition
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
                $transition
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
                $transition
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
            $transition
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
        array $transition
    ): string {
        if ($this->_layoutDirection !== 'vertical') {
            return $this->transitionSvgHorizontal(
                $class,
                $id,
                $path,
                $label,
                $labelX,
                $labelY,
                $semanticLabel
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
        if (!$fixedCard) {
            [$labelX, $labelY] = $this->reserveVerticalTransitionLabel(
                $labelX,
                $labelY,
                $labelWidth,
                $labelHeight
            );
        }

        $labelLeader = '';
        $leaderDistance = hypot($labelX - $anchorX, $labelY - $anchorY);
        if ($leaderDistance >= 18.0) {
            $labelLeader = '<path class="fsm-label-leader" d="M'
                . self::n($anchorX) . ' ' . self::n($anchorY)
                . ' L' . self::n($labelX) . ' ' . self::n($labelY)
                . '" />';
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

        $origin = str_contains($class, 'signal-origin-user')
            ? 'user'
            : (str_contains($class, 'signal-origin-automatic')
                ? 'automatic'
                : 'unspecified');

        $edgeSvg = $path === ''
            ? ''
            : '<path class="fsm-edge" d="' . $path
                . '" marker-end="url(#fsm-arrow-' . self::h($origin) . ')" />';

        return '<g class="' . self::h($class)
            . '" data-transition-id="' . self::h($id)
            . '" data-signal-origin="' . self::h($origin) . '">'
            . '<title>' . self::h($semanticLabel) . '</title>'
            . $edgeSvg
            . $labelLeader
            . $labelSvg
            . '</g>';
    }

    private function transitionSvgHorizontal(
        string $class,
        string $id,
        string $path,
        string $label,
        float $labelX,
        float $labelY,
        string $semanticLabel
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
        [$labelX, $labelY] = $this->reserveTransitionLabel(
            $labelX,
            $labelY,
            $labelWidth,
            $labelHeight
        );

        $labelLeader = '';
        $leaderDistance = hypot($labelX - $anchorX, $labelY - $anchorY);
        if ($leaderDistance >= 18.0) {
            $labelLeader = '<path class="fsm-label-leader" d="M'
                . self::n($anchorX) . ' ' . self::n($anchorY - 5.0)
                . ' L' . self::n($labelX) . ' ' . self::n($labelY - 5.0)
                . '" />';
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

        $origin = str_contains($class, 'signal-origin-user')
            ? 'user'
            : (str_contains($class, 'signal-origin-automatic')
                ? 'automatic'
                : 'unspecified');

        return '<g class="' . self::h($class)
            . '" data-transition-id="' . self::h($id)
            . '" data-signal-origin="' . self::h($origin) . '">'
            . '<title>' . self::h($semanticLabel) . '</title>'
            . '<path class="fsm-edge" d="' . $path
            . '" marker-end="url(#fsm-arrow-' . self::h($origin) . ')" />'
            . $labelLeader
            . $labelSvg
            . '</g>';
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
        if ($this->_layoutDirection === 'vertical') {
            $cx = $target['x'] + $target['w'] / 2;
            $cy = max(28.0, $target['y'] - 54.0);
            return '<g class="fsm-initial-marker" aria-label="initial">'
                . '<circle cx="' . self::n($cx)
                . '" cy="' . self::n($cy)
                . '" r="9" />'
                . '<path class="fsm-edge" d="M'
                . self::n($cx) . ' ' . self::n($cy + 10.0)
                . ' L' . self::n($cx) . ' ' . self::n($target['y'])
                . '" marker-end="url(#fsm-arrow-unspecified)" />'
                . '</g>';
        }

        $cx = max(28.0, $target['x'] - 54.0);
        $cy = $target['y'] + $target['h'] / 2;

        return '<g class="fsm-initial-marker" aria-label="initial">'
            . '<circle cx="' . self::n($cx)
            . '" cy="' . self::n($cy)
            . '" r="9" />'
            . '<path class="fsm-edge" d="M'
            . self::n($cx + 10)
            . ' '
            . self::n($cy)
            . ' L'
            . self::n($target['x'])
            . ' '
            . self::n($cy)
            . '" marker-end="url(#fsm-arrow)" />'
            . '</g>';
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

        return '<g class="fsm-final-marker" aria-label="final">'
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
