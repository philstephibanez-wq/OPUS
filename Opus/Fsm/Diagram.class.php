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

    private bool $_compactLayout = false;

    /** @var list<string> */
    private array $_fallbackEffects = [];

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

            if ($from === '' || $to === '' || $signal === '') {
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
        array $transitionLinks = []
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
        $diagram->setTransitionLinks($transitionLinks);
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
        array $runtimeOperations
    ): void {
        $from = trim($from);
        $to = trim($to);
        $signal = trim($signal);
        if ($from === '' || $to === '' || $signal === '') {
            return;
        }

        if ($from !== '*') {
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
            'guards' => array_values($guards),
            'actions' => array_values($actions),
            'runtime_operations' => array_values($runtimeOperations),
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

        $svg = '<svg class="fsm-diagram" width="'
            . self::n($width)
            . '" height="'
            . self::n($height)
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
        foreach ($this->_transitions as $transition) {
            $key = $transition['from'] . "\0" . $transition['to'];
            $pairTotals[$key] = ($pairTotals[$key] ?? 0) + 1;
        }

        foreach ($this->_transitions as $transition) {
            $key = $transition['from'] . "\0" . $transition['to'];
            $pairOrdinals[$key] = ($pairOrdinals[$key] ?? 0) + 1;
            $svg .= $this->renderTransition(
                $transition,
                $positions,
                $pairOrdinals[$key] - 1,
                $pairTotals[$key]
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
     * @param list<string> $states
     * @return array{
     *   positions:array<string,array{x:float,y:float,w:float,h:float,rank:int}>,
     *   width:float,
     *   height:float
     * }
     */
    private function layout(array $states): array
    {
        $nodeW = $this->_compactLayout ? 176.0 : 204.0;
        $nodeH = $this->_compactLayout ? 68.0 : 76.0;
        $rankGap = $this->_compactLayout ? 38.0 : 150.0;
        $rowGap = $this->_compactLayout ? 48.0 : 66.0;
        $marginX = $this->_compactLayout ? 48.0 : 110.0;
        $marginY = $this->hasGlobalSource()
            ? ($this->_compactLayout ? 126.0 : 158.0)
            : ($this->_compactLayout ? 76.0 : 98.0);

        $ranks = [];
        if ($this->_initialState !== ''
            && isset($this->_states[$this->_initialState])) {
            $ranks[$this->_initialState] = 0;
            $queue = [$this->_initialState];

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

        $byRank = [];
        foreach ($states as $state) {
            $rank = $ranks[$state];
            $byRank[$rank] ??= [];
            $byRank[$rank][] = $state;
        }
        ksort($byRank, SORT_NUMERIC);

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
                + ($this->_compactLayout ? 92.0 : 160.0)
        );

        return [
            'positions' => $positions,
            'width' => $width,
            'height' => $height,
        ];
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
        int $total
    ): string {
        $to = $transition['to'];
        if (!isset($positions[$to])) {
            return '';
        }

        $class = 'fsm-transition';
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
                $semanticLabel
            );
        }

        $from = $transition['from'];
        if (!isset($positions[$from])) {
            return '';
        }

        $fromPos = $positions[$from];

        if ($from === $to) {
            $loop = 38 + $ordinal * 24;
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
            $labelY = $y - $loop - 9;

            return $this->transitionSvg(
                $class . ' self-loop',
                $id,
                $path,
                $label,
                $labelX,
                $labelY,
                $semanticLabel
            );
        }

        $forward = $toPos['rank'] > $fromPos['rank'];
        $sameRank = $toPos['rank'] === $fromPos['rank'];

        if ($forward) {
            $x1 = $fromPos['x'] + $fromPos['w'];
            $y1 = $fromPos['y'] + $fromPos['h'] / 2;
            $x2 = $toPos['x'];
            $y2 = $toPos['y'] + $toPos['h'] / 2;
        } elseif ($sameRank) {
            $x1 = $fromPos['x'] + $fromPos['w'] / 2;
            $y1 = $fromPos['y'] + $fromPos['h'];
            $x2 = $toPos['x'] + $toPos['w'] / 2;
            $y2 = $toPos['y'];
        } else {
            $x1 = $fromPos['x'];
            $y1 = $fromPos['y'] + $fromPos['h'] / 2;
            $x2 = $toPos['x'] + $toPos['w'];
            $y2 = $toPos['y'] + $toPos['h'] / 2;
        }

        $spread = ($ordinal - (($total - 1) / 2)) * 30.0;
        $distance = max(60.0, abs($x2 - $x1) * 0.44);

        if ($sameRank) {
            $controlY = min($y1, $y2) - 55.0 - abs($spread);
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
            $labelY = $controlY - 8;
        } else {
            $curveY = $spread;
            if (!$forward) {
                $curveY -= 46.0;
            }
            $path = 'M' . self::n($x1) . ' ' . self::n($y1)
                . ' C'
                . self::n($forward ? $x1 + $distance : $x1 - $distance)
                . ' '
                . self::n($y1 + $curveY)
                . ', '
                . self::n($forward ? $x2 - $distance : $x2 + $distance)
                . ' '
                . self::n($y2 + $curveY)
                . ', '
                . self::n($x2) . ' '
                . self::n($y2);
            $labelX = ($x1 + $x2) / 2;
            $labelY = ($y1 + $y2) / 2 + $curveY - 10;
        }

        return $this->transitionSvg(
            $class . (!$forward ? ' return-edge' : ''),
            $id,
            $path,
            $label,
            $labelX,
            $labelY,
            $semanticLabel
        );
    }

    private function transitionSvg(
        string $class,
        string $id,
        string $path,
        string $label,
        float $labelX,
        float $labelY,
        string $semanticLabel
    ): string {
        $labelSvg = '<text class="fsm-edge-label" x="'
            . self::n($labelX)
            . '" y="'
            . self::n($labelY)
            . '">'
            . self::h($label)
            . '</text>';

        $link = $this->_transitionLinks[$id] ?? null;
        if (is_string($link)) {
            $labelSvg = '<a class="fsm-signal-link" href="'
                . self::h($link)
                . '" aria-label="'
                . self::h($label)
                . '">'
                . $labelSvg
                . '</a>';
        }

        return '<g class="' . self::h($class)
            . '" data-transition-id="' . self::h($id) . '">'
            . '<title>' . self::h($semanticLabel) . '</title>'
            . '<path class="fsm-edge" d="' . $path
            . '" marker-end="url(#fsm-arrow)" />'
            . $labelSvg
            . '</g>';
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
  <marker id="fsm-arrow" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
    <path d="M0,0 L0,6 L9,3 z" class="fsm-arrow-head" />
  </marker>
  <style>
    .fsm-diagram { width:auto; max-width:none; height:auto; overflow:visible; font-family:"Segoe UI",Arial,sans-serif; }
    .fsm-title { fill:var(--opus-fsm-text,#e8f5ff); font-size:19px; font-weight:800; }
    .fsm-subtitle { fill:var(--opus-fsm-muted,#9fb4cf); font-size:12px; }
    .fsm-node rect { fill:var(--opus-fsm-node-bg,#101c2f); stroke:var(--opus-fsm-node-border,#6b829e); stroke-width:1.5; }
    .fsm-node.current rect { fill:var(--opus-fsm-current-bg,#17365d); stroke:var(--opus-fsm-current-border,#6ce3ff); stroke-width:3; }
    .fsm-node-link { cursor:pointer; text-decoration:none; }
    .fsm-node-link:hover .fsm-node rect,
    .fsm-node-link:focus .fsm-node rect { stroke:var(--opus-fsm-focus,#fbbf24); stroke-width:3; }
    .fsm-signal-link { cursor:pointer; text-decoration:none; }
    .fsm-signal-link .fsm-edge-label { fill:var(--opus-fsm-signal,#6ce3ff); text-decoration:underline; font-weight:800; }
    .fsm-signal-link:hover .fsm-edge-label,
    .fsm-signal-link:focus .fsm-edge-label { fill:var(--opus-fsm-focus,#fbbf24); }
    .fsm-state-label { fill:var(--opus-fsm-state-text,#f6f8ff); font-size:13px; font-weight:800; text-anchor:middle; }
    .fsm-node-tag { fill:var(--opus-fsm-signal,#6ce3ff); font-size:10px; font-weight:800; text-anchor:middle; }
    .fsm-state-annotation { fill:var(--opus-fsm-muted,#b8c5de); font-size:9px; text-anchor:middle; }
    .fsm-edge { fill:none; stroke:var(--opus-fsm-edge,#7da4c8); stroke-width:1.7; }
    .fsm-arrow-head { fill:var(--opus-fsm-edge,#7da4c8); }
    .fsm-transition.wildcard .fsm-edge,
    .fsm-transition.fallback .fsm-edge { stroke-dasharray:6 5; }
    .fsm-transition.return-edge .fsm-edge { stroke:var(--opus-fsm-return,#a78bfa); }
    .fsm-transition.self-loop .fsm-edge { stroke:var(--opus-fsm-loop,#fbbf24); }
    .fsm-edge-label { fill:var(--opus-fsm-label,#dbeafe); font-size:11px; font-weight:650; text-anchor:middle; paint-order:stroke; stroke:var(--opus-fsm-label-halo,#07111f); stroke-width:4px; stroke-linejoin:round; }
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
