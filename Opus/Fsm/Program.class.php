<?php

/**
 * Generic OPUS FSM program executor.
 *
 * OPUS contract:
 * - the FSM is the runtime processor;
 * - transitions are the executable instruction program;
 * - boot/runtime flows must be expressed as data, not as a dedicated Boot class;
 * - memory, stack, NMI and events are first-class runtime state.
 */
#[AllowDynamicProperties]
/**
 * OPUS FSM program container.
 *
 * Stores OPUS FSM program definitions and transition data.
 */
class OPUS_FSM_Program  implements OPUS_FSM_ProgramInterface {
    private string $_id;
    private string $_state;
    private string $_initialState;
    private string $_readyState;
    private string $_nmiState;
    private array $_transitions = array();
    private array $_bootSequence = array();
    private array $_memory = array();
    private array $_stack = array();
    private array $_events = array();

    /**
     * @param string $id Stable runtime program id.
     * @param array $program Program definition loaded from configuration.
     */
    public function __construct(string $id, array $program) {
        $this->_id = $id;
        $this->_initialState = $this->_requireString($program, 'initial_state');
        $this->_readyState = $this->_requireString($program, 'ready_state');
        $this->_nmiState = $this->_optionalString($program, 'nmi_state', 'NMI');
        $this->_state = $this->_initialState;
        $this->_bootSequence = $this->_requireStringList($program, 'boot_sequence');
        $this->_transitions = $this->_compileTransitions($program['transitions'] ?? null);
        $this->_memory = is_array($program['memory'] ?? null) ? $program['memory'] : array();
        $this->_emit('PROGRAM_LOADED', array(
            'id' => $this->_id,
            'initial_state' => $this->_initialState,
            'ready_state' => $this->_readyState,
            'transition_count' => count($this->_transitions),
        ));
    }

    /**
     * Load a FSM program from a PHP file returning a strict array.
     */
    public static function fromFile(string $id, string $file): self {
        if (!is_file($file)) {
            self::_fail('OPUS FSM program file not found: ' . $file);
        }

        $program = require $file;
        if (!is_array($program)) {
            self::_fail('OPUS FSM program file must return an array: ' . $file);
        }

        return new self($id, $program);
    }

    public function getId(): string {
        return $this->_id;
    }

    public function getState(): string {
        return $this->_state;
    }

    public function isReady(): bool {
        return $this->_state === $this->_readyState;
    }

    public function getMemory(): array {
        return $this->_memory;
    }

    public function getStack(): array {
        return $this->_stack;
    }

    public function getEvents(): array {
        return $this->_events;
    }

    public function writeMemory(string $key, $value): void {
        if ($key === '') {
            self::_fail('OPUS FSM memory key must not be empty.');
        }
        $this->_memory[$key] = $value;
        $this->_emit('MEMORY_WRITE', array('key' => $key));
    }

    public function readMemory(string $key, $default = null) {
        return array_key_exists($key, $this->_memory) ? $this->_memory[$key] : $default;
    }

    /**
     * Execute the configured boot sequence, or a supplied sequence of signals.
     */
    public function run(?array $signals = null): string {
        $sequence = $signals === null ? $this->_bootSequence : $signals;
        foreach ($sequence as $signal) {
            if (!is_string($signal) || $signal === '') {
                self::_fail('OPUS FSM sequence contains an invalid signal.');
            }
            $this->signal($signal);
        }
        return $this->_state;
    }

    /**
     * Execute one transition instruction.
     */
    public function signal(string $signal, array $payload = array()): string {
        if ($signal === '') {
            self::_fail('OPUS FSM signal must not be empty.');
        }

        $transition = $this->_findTransition($this->_state, $signal);
        if ($transition === null) {
            self::_fail('OPUS FSM transition not found: state=' . $this->_state . ' signal=' . $signal);
        }

        $previous = $this->_state;
        $this->_state = $transition['to'];
        $frame = array(
            'from' => $previous,
            'signal' => $signal,
            'to' => $this->_state,
            'action' => $transition['action'],
            'payload' => $payload,
            'at' => gmdate('c'),
        );
        $this->_stack[] = $frame;
        $this->_emit('TRANSITION', $frame);
        return $this->_state;
    }

    /**
     * Trigger a non-maskable interrupt. This is explicit and never silent.
     */
    public function nmi(string $reason, array $payload = array()): string {
        if ($reason === '') {
            $reason = 'OPUS_FSM_NMI';
        }
        $previous = $this->_state;
        $this->_state = $this->_nmiState;
        $frame = array(
            'from' => $previous,
            'signal' => 'NMI',
            'to' => $this->_state,
            'action' => 'NMI',
            'reason' => $reason,
            'payload' => $payload,
            'at' => gmdate('c'),
        );
        $this->_stack[] = $frame;
        $this->_emit('NMI', $frame);
        return $this->_state;
    }

    private function _findTransition(string $from, string $signal): ?array {
        foreach ($this->_transitions as $transition) {
            if ($transition['from'] === $from && $transition['signal'] === $signal) {
                return $transition;
            }
        }
        return null;
    }

    private function _compileTransitions($transitions): array {
        if (!is_array($transitions) || count($transitions) === 0) {
            self::_fail('OPUS FSM program requires at least one transition.');
        }

        $compiled = array();
        foreach ($transitions as $index => $transition) {
            if (!is_array($transition)) {
                self::_fail('OPUS FSM transition #' . $index . ' must be an array.');
            }
            $compiled[] = array(
                'from' => $this->_requireString($transition, 'from'),
                'signal' => $this->_requireString($transition, 'signal'),
                'to' => $this->_requireString($transition, 'to'),
                'action' => $this->_optionalString($transition, 'action', ''),
            );
        }
        return $compiled;
    }

    private function _emit(string $name, array $payload): void {
        $this->_events[] = array(
            'event' => $name,
            'state' => $this->_state,
            'payload' => $payload,
            'at' => gmdate('c'),
        );
    }

    private function _requireString(array $source, string $key): string {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            self::_fail('OPUS FSM program requires non-empty string key: ' . $key);
        }
        return $value;
    }

    private function _optionalString(array $source, string $key, string $default): string {
        $value = $source[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            self::_fail('OPUS FSM program optional string is invalid: ' . $key);
        }
        return $value;
    }

    private function _requireStringList(array $source, string $key): array {
        $value = $source[$key] ?? null;
        if (!is_array($value) || count($value) === 0) {
            self::_fail('OPUS FSM program requires non-empty string list: ' . $key);
        }
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                self::_fail('OPUS FSM program list contains an invalid item: ' . $key);
            }
        }
        return array_values($value);
    }

    private static function _fail(string $message): void {
        if (class_exists('OPUS_Exception')) {
            throw new OPUS_Exception($message);
        }
        throw new RuntimeException($message);
    }
}
