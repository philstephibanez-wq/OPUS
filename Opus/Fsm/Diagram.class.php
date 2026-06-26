<?php

/**
 * Lightweight FSM diagram renderer for OPUS.
 *
 * This replaces the historical GraphViz/dot dependency with a self-contained
 * SVG renderer: no external binary, no exec(), no temporary image file.
 */
class OPUS_FSM_Diagram  implements OPUS_FSM_DiagramInterface {
    private string $_title;
    private string $_initialState;
    private string $_finalState;
    private string $_currentState;
    private array $_memory;
    private array $_states = array();
    private array $_edges = array();
    private array $_actions = array();

    public function __construct(string $title = 'OPUS FSM', string $initialState = '', string $finalState = '', string $currentState = '', array $memory = array()) {
        $this->_title = $title !== '' ? $title : 'OPUS FSM';
        $this->_initialState = $initialState;
        $this->_finalState = $finalState;
        $this->_currentState = $currentState;
        $this->_memory = $memory;

        if ($initialState !== '') {
            $this->addState($initialState);
        }
        if ($finalState !== '') {
            $this->addState($finalState);
        }
        if ($currentState !== '') {
            $this->addState($currentState);
        }
    }

    public static function renderRuntime(string $title, string $initialState, string $finalState, string $currentState, array $memory, array $transitions): string {
        return self::fromTransitions($title, $initialState, $finalState, $currentState, $memory, $transitions)->renderHtml();
    }

    public static function fromTransitions(string $title, string $initialState, string $finalState, string $currentState, array $memory, array $transitions): self {
        $diagram = new self($title, $initialState, $finalState, $currentState, $memory);

        foreach ($transitions as $transition) {
            if (!is_object($transition)) {
                continue;
            }

            $signal = isset($transition->signal) ? (string)$transition->signal : '';
            $state = isset($transition->state) ? (string)$transition->state : '';
            $nextState = isset($transition->nextState) ? (string)$transition->nextState : '';
            $action = isset($transition->action) ? (string)$transition->action : '';

            if ($signal === '__default__') {
                if ($action !== '') {
                    $diagram->addAction('DEFAULT', $action, true);
                }
                continue;
            }

            if ($state !== '') {
                $diagram->addState($state);
            }
            if ($nextState !== '') {
                $diagram->addState($nextState);
            }
            if ($state !== '' && $nextState !== '') {
                $diagram->addEdge($state, $nextState, $signal === '__any__' ? 'any' : $signal);
            }
            if ($state !== '' && $action !== '') {
                $diagram->addAction($state, $action, false);
            }
        }

        return $diagram;
    }

    public static function renderDemoHtml(): string {
        $diagram = new self('OPUS demo FSM', 'IDLE', 'DONE', 'ROUTE_FOUND', array(
            'url' => '/fr/démo-interne',
            'page' => 'default',
            'language' => 'FR-fr',
        ));

        $diagram->addEdge('IDLE', 'BOOTSTRAP', 'HTTP_REQUEST');
        $diagram->addAction('BOOTSTRAP', 'loadConfig');
        $diagram->addEdge('BOOTSTRAP', 'ROUTE_FOUND', 'ROUTE_MATCH');
        $diagram->addAction('ROUTE_FOUND', 'dispatchController');
        $diagram->addEdge('ROUTE_FOUND', 'VIEW_READY', 'ACTION_OK');
        $diagram->addAction('VIEW_READY', 'drawTemplate');
        $diagram->addEdge('VIEW_READY', 'DONE', 'RENDER');

        return $diagram->renderHtml();
    }

    public function addState(string $name): void {
        if ($name === '') {
            return;
        }
        $this->_states[$name] = $name;
    }

    public function addEdge(string $from, string $to, string $label = ''): void {
        if ($from === '' || $to === '') {
            return;
        }
        $this->addState($from);
        $this->addState($to);

        $key = $from . "\0" . $to;
        if (!isset($this->_edges[$key])) {
            $this->_edges[$key] = array('from' => $from, 'to' => $to, 'labels' => array());
        }
        if ($label !== '' && !in_array($label, $this->_edges[$key]['labels'], true)) {
            $this->_edges[$key]['labels'][] = $label;
        }
    }

    public function addAction(string $state, string $action, bool $isDefault = false): void {
        if ($action === '') {
            return;
        }
        $this->_actions[] = array('state' => $state, 'action' => $action, 'default' => $isDefault);
    }

    public function renderHtml(): string {
        return '<div class="fsm-diagram-card">'
            . '<div class="fsm-diagram-toolbar"><strong>' . self::h($this->_title) . '</strong><span>SVG natif · zéro GraphViz · zéro exec()</span></div>'
            . $this->renderSvg()
            . '<p class="fsm-diagram-note">Rendu généré côté PHP en SVG inline : portable sur UwAmp, Cloudflare Tunnel ou hébergement mutualisé, sans binaire serveur.</p>'
            . '</div>';
    }

    public function renderSvg(): string {
        $states = array_values($this->_states);
        if (!$states) {
            $states = array('EMPTY');
        }

        $cols = min(3, max(1, count($states)));
        $nodeW = 188;
        $nodeH = 62;
        $xGap = 82;
        $yGap = 106;
        $marginX = 36;
        $marginY = 92;
        $rows = (int)ceil(count($states) / $cols);
        $width = max(620, $marginX * 2 + $cols * $nodeW + ($cols - 1) * $xGap + 220);
        $height = max(360, $marginY + $rows * ($nodeH + $yGap) + 96 + min(4, max(1, count($this->_memory))) * 26);

        $positions = array();
        foreach ($states as $i => $state) {
            $row = intdiv($i, $cols);
            $col = $i % $cols;
            if ($row % 2 === 1) {
                $col = $cols - 1 - $col;
            }
            $positions[$state] = array(
                'x' => $marginX + $col * ($nodeW + $xGap),
                'y' => $marginY + $row * ($nodeH + $yGap),
                'w' => $nodeW,
                'h' => $nodeH,
            );
        }

        $svg = '<svg class="fsm-diagram" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Diagramme FSM OPUS">';
        $svg .= '<defs>'
            . '<marker id="fsm-arrow" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L0,6 L9,3 z" /></marker>'
            . '<linearGradient id="fsm-state-fill" x1="0" x2="1" y1="0" y2="1"><stop offset="0%" stop-color="#ffffff"/><stop offset="100%" stop-color="#eef6ff"/></linearGradient>'
            . '<linearGradient id="fsm-current-fill" x1="0" x2="1" y1="0" y2="1"><stop offset="0%" stop-color="#fff7ed"/><stop offset="100%" stop-color="#ffe8c2"/></linearGradient>'
            . '</defs>';

        $svg .= '<text x="36" y="42" class="fsm-title">' . self::h($this->_title) . '</text>';
        $svg .= '<text x="36" y="66" class="fsm-subtitle">Signal → transition → action → état suivant</text>';

        foreach ($this->_edges as $edge) {
            if (!isset($positions[$edge['from']], $positions[$edge['to']])) {
                continue;
            }
            $from = $positions[$edge['from']];
            $to = $positions[$edge['to']];
            $x1 = $from['x'] + $from['w'];
            $y1 = $from['y'] + $from['h'] / 2;
            $x2 = $to['x'];
            $y2 = $to['y'] + $to['h'] / 2;

            if ($x2 < $x1) {
                $x1 = $from['x'] + $from['w'] / 2;
                $y1 = $from['y'] + $from['h'];
                $x2 = $to['x'] + $to['w'] / 2;
                $y2 = $to['y'];
            }

            $midX = ($x1 + $x2) / 2;
            $midY = ($y1 + $y2) / 2 - 10;
            $c1 = $x1 + max(40, abs($x2 - $x1) / 2);
            $c2 = $x2 - max(40, abs($x2 - $x1) / 2);
            if ($x2 < $x1) {
                $c1 = $x1;
                $c2 = $x2;
            }
            $label = implode(', ', $edge['labels']);
            $path = 'M' . self::n($x1) . ' ' . self::n($y1) . ' C' . self::n($c1) . ' ' . self::n($y1) . ', ' . self::n($c2) . ' ' . self::n($y2) . ', ' . self::n($x2) . ' ' . self::n($y2);
            $svg .= '<path class="fsm-edge" d="' . $path . '" marker-end="url(#fsm-arrow)" />';
            if ($label !== '') {
                $svg .= '<text class="fsm-edge-label" x="' . self::n($midX) . '" y="' . self::n($midY) . '">' . self::h($label) . '</text>';
            }
        }

        foreach ($positions as $state => $pos) {
            $classes = 'fsm-node';
            if ($state === $this->_initialState) {
                $classes .= ' initial';
            }
            if ($state === $this->_finalState) {
                $classes .= ' final';
            }
            if ($state === $this->_currentState) {
                $classes .= ' current';
            }
            $svg .= '<g class="' . $classes . '">';
            $svg .= '<rect x="' . self::n($pos['x']) . '" y="' . self::n($pos['y']) . '" width="' . self::n($pos['w']) . '" height="' . self::n($pos['h']) . '" rx="18" />';
            $svg .= '<text x="' . self::n($pos['x'] + $pos['w'] / 2) . '" y="' . self::n($pos['y'] + 34) . '">' . self::h($state) . '</text>';
            $tag = $this->stateTag($state);
            if ($tag !== '') {
                $svg .= '<text class="fsm-node-tag" x="' . self::n($pos['x'] + $pos['w'] / 2) . '" y="' . self::n($pos['y'] + 52) . '">' . self::h($tag) . '</text>';
            }
            $svg .= '</g>';
        }

        $actionX = $width - 200;
        $actionY = 92;
        $svg .= '<g class="fsm-actions"><text x="' . self::n($actionX) . '" y="68">Actions</text>';
        $visibleActions = array_slice($this->_actions, 0, 7);
        foreach ($visibleActions as $i => $action) {
            $y = $actionY + $i * 44;
            $class = !empty($action['default']) ? ' default' : '';
            $svg .= '<g class="fsm-action' . $class . '"><rect x="' . self::n($actionX) . '" y="' . self::n($y) . '" width="164" height="32" rx="10" />';
            $svg .= '<text x="' . self::n($actionX + 82) . '" y="' . self::n($y + 21) . '">' . self::h($action['action'] . '()') . '</text></g>';
        }
        if (count($this->_actions) > count($visibleActions)) {
            $svg .= '<text class="fsm-more" x="' . self::n($actionX + 82) . '" y="' . self::n($actionY + count($visibleActions) * 44 + 18) . '">+' . (count($this->_actions) - count($visibleActions)) . ' actions</text>';
        }
        $svg .= '</g>';

        $memoryY = $height - 86;
        $svg .= '<g class="fsm-memory"><rect x="36" y="' . self::n($memoryY - 28) . '" width="' . self::n($width - 72) . '" height="70" rx="18" />';
        $svg .= '<text x="56" y="' . self::n($memoryY) . '">Memory</text>';
        $memX = 142;
        $memCount = 0;
        foreach ($this->_memory as $key => $value) {
            if ($memCount >= 4) {
                break;
            }
            $valueText = is_object($value) ? 'Object' : (is_array($value) ? 'Array' : strip_tags((string)$value));
            $text = self::shorten((string)$key . '=' . $valueText, 26);
            $svg .= '<text class="fsm-memory-item" x="' . self::n($memX) . '" y="' . self::n($memoryY) . '">' . self::h($text) . '</text>';
            $memX += 184;
            $memCount++;
        }
        if (!$this->_memory) {
            $svg .= '<text class="fsm-memory-item" x="142" y="' . self::n($memoryY) . '">vide</text>';
        }
        $svg .= '</g>';

        $svg .= '</svg>';
        return $svg;
    }

    private function stateTag(string $state): string {
        if ($state === $this->_currentState) {
            return 'current';
        }
        if ($state === $this->_initialState) {
            return 'initial';
        }
        if ($state === $this->_finalState) {
            return 'final';
        }
        return '';
    }


    private static function shorten(string $text, int $max): string {
        if ($max <= 0) {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max - 1, 'UTF-8') . '…' : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max - 1) . '…' : $text;
    }

    private static function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function n($value): string {
        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }
}

?>
