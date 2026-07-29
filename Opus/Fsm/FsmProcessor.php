<?php
declare(strict_types=1);

namespace Opus\Fsm;

use InvalidArgumentException;
use Opus\File\StructuredFileLoader;
use RuntimeException;

/**
 * Executes OPUS finite state machines from versioned configuration arrays.
 *
 * The processor is deliberately small and strict: it never invents a fallback
 * state, never ignores an unknown guard, and refuses ambiguous transitions.
 * State, memory and stack belong to the processor. Persistence is delegated to
 * an explicit store, but callers must not duplicate FSM state ownership.
 */
final class FsmProcessor implements FsmProcessorInterface
{
    private const RESULT_CONTRACT = 'OPUS_FSM_PROCESSOR_RESULT_V1';

    /** @var array<string,true> */
    private const CANONICAL_CONTRACTS = [
        'OPUS_APPLICATION_FSM_V1' => true,
        'OPUS_FSM_REGISTRY_V1' => true,
    ];

    /** @var array<string,mixed> */
    private array $fsm;

    /** @var array<string,array<string,mixed>> */
    private array $statesById = [];

    /** @var array<string,callable> */
    private array $guardHandlers = [];

    private string $currentState;

    /** @var array<string,mixed> */
    private array $memory = [];

    /** @var list<mixed> */
    private array $stack = [];

    private string $stackType = 'fifo';

    /**
     * @param array<string,mixed> $fsm
     * @param array<string,callable> $guardHandlers
     */
    public function __construct(array $fsm, array $guardHandlers = [])
    {
        $this->guardHandlers = $guardHandlers;
        $this->fsm = $fsm;
        $this->validateFsm();
        $this->currentState = $this->initialState();
    }

    /**
     * Loads a processor from a structured FSM configuration file.
     *
     * @param array<string,callable> $guardHandlers
     */
    public static function fromJsonFile(
        string $path,
        array $guardHandlers = []
    ): self {
        try {
            $decoded = StructuredFileLoader::instance()->read($path);
        } catch (\Throwable $cause) {
            throw new RuntimeException('OPUS_FSM_JSON_INVALID: ' . $path, 0, $cause);
        }

        return new self($decoded, $guardHandlers);
    }

    public function contract(): string
    {
        return (string) $this->fsm['contract'];
    }

    public function initialState(): string
    {
        return (string) $this->fsm['initial_state'];
    }

    public function currentState(): string
    {
        return $this->currentState;
    }

    public function reset(): void
    {
        $this->currentState = $this->initialState();
        $this->memory = [];
        $this->stack = [];
        $this->stackType = 'fifo';
    }

    /** @return array<string,mixed> */
    public function memory(): array
    {
        return $this->memory;
    }

    public function peek(string $name): mixed
    {
        if ($name === '' || !array_key_exists($name, $this->memory)) {
            throw new RuntimeException('OPUS_FSM_MEMORY_KEY_UNKNOWN: ' . $name);
        }
        return $this->memory[$name];
    }

    public function poke(string $name, mixed $value): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('OPUS_FSM_MEMORY_KEY_REQUIRED');
        }
        $this->memory[$name] = $value;
    }

    /** @return list<mixed> */
    public function stack(): array
    {
        return $this->stack;
    }

    public function push(mixed $value): void
    {
        $this->stack[] = $value;
    }

    public function pop(): mixed
    {
        if ($this->stack === []) {
            return null;
        }
        return $this->stackType === 'lifo'
            ? array_pop($this->stack)
            : array_shift($this->stack);
    }

    public function setStackType(string $type): void
    {
        if (!in_array($type, ['fifo', 'lifo'], true)) {
            throw new InvalidArgumentException(
                'OPUS_FSM_STACK_TYPE_INVALID: ' . $type
            );
        }
        $this->stackType = $type;
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return [
            'contract' => 'OPUS_FSM_RUNTIME_SNAPSHOT_V1',
            'fsm_contract' => $this->contract(),
            'state' => $this->currentState,
            'memory' => $this->memory,
            'stack' => $this->stack,
            'stack_type' => $this->stackType,
        ];
    }

    /** @param array<string,mixed> $snapshot */
    public function restore(array $snapshot): void
    {
        if (($snapshot['contract'] ?? null) !== 'OPUS_FSM_RUNTIME_SNAPSHOT_V1'
            || ($snapshot['fsm_contract'] ?? null) !== $this->contract()) {
            throw new RuntimeException('OPUS_FSM_RUNTIME_SNAPSHOT_INVALID');
        }
        $state = (string) ($snapshot['state'] ?? '');
        if (!$this->hasState($state)) {
            throw new RuntimeException(
                'OPUS_FSM_RUNTIME_SNAPSHOT_STATE_UNKNOWN: ' . $state
            );
        }
        if (!is_array($snapshot['memory'] ?? null)
            || !is_array($snapshot['stack'] ?? null)) {
            throw new RuntimeException('OPUS_FSM_RUNTIME_SNAPSHOT_DATA_INVALID');
        }
        $this->setStackType((string) ($snapshot['stack_type'] ?? ''));
        $this->currentState = $state;
        $this->memory = $snapshot['memory'];
        $this->stack = array_values($snapshot['stack']);
    }

    /** @return array<string,mixed> */
    public function state(string $stateId): array
    {
        if (!isset($this->statesById[$stateId])) {
            throw new RuntimeException('OPUS_FSM_STATE_UNKNOWN: ' . $stateId);
        }

        return $this->statesById[$stateId];
    }

    public function hasState(string $stateId): bool
    {
        return isset($this->statesById[$stateId]);
    }

    /**
     * Executes a transition for a current state and event.
     *
     * @param array<string,mixed> $context Runtime facts available to guards.
     * @return array<string,mixed>
     */
    public function transition(
        string $currentState,
        string $event,
        array $context = []
    ): array {
        if ($currentState === '' || !isset($this->statesById[$currentState])) {
            throw new RuntimeException(
                'OPUS_FSM_CURRENT_STATE_UNKNOWN: ' . $currentState
            );
        }
        if ($event === '') {
            throw new RuntimeException('OPUS_FSM_EVENT_REQUIRED');
        }

        $this->currentState = $currentState;
        $transition = $this->findTransition($currentState, $event);
        if ($transition === null) {
            throw new RuntimeException(
                'OPUS_FSM_TRANSITION_NOT_FOUND: '
                . $currentState . ':' . $event
            );
        }

        $target = (string) ($transition['to'] ?? '');
        if ($target === '' || !isset($this->statesById[$target])) {
            throw new RuntimeException(
                'OPUS_FSM_TARGET_STATE_UNKNOWN: ' . $target
            );
        }

        foreach ($this->transitionGuards($transition) as $guard) {
            if (!$this->evaluateGuard(
                $guard,
                $currentState,
                $event,
                $transition,
                $context
            )) {
                throw new RuntimeException('OPUS_FSM_GUARD_FAILED: ' . $guard);
            }
        }

        $actions = $this->transitionActions($transition);
        $this->currentState = $target;
        $this->applyMemoryOperations($transition, $context);

        return [
            'contract' => self::RESULT_CONTRACT,
            'fsm_contract' => $this->contract(),
            'from_state' => $currentState,
            'event' => $event,
            'to_state' => $target,
            'transition_id' => (string) ($transition['id'] ?? ''),
            'guards' => $this->transitionGuards($transition),
            'actions' => $actions,
            'action' => $actions[0] ?? '',
            'target_state' => $this->statesById[$target],
            'runtime' => $this->snapshot(),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function transitions(): array
    {
        return array_values($this->fsm['transitions']);
    }


    private static function supportsContract(string $contract): bool
    {
        if (isset(self::CANONICAL_CONTRACTS[$contract])) {
            return true;
        }

        return preg_match(
            '/^[A-Z][A-Z0-9_]*_FSM_V[1-9][0-9]*$/D',
            $contract
        ) === 1;
    }

    private function validateFsm(): void
    {
        $contract = (string) ($this->fsm['contract'] ?? '');
        if (!self::supportsContract($contract)) {
            throw new InvalidArgumentException(
                'OPUS_FSM_CONTRACT_INVALID: ' . $contract
            );
        }

        $states = $this->fsm['states'] ?? null;
        if (!is_array($states) || $states === []) {
            throw new InvalidArgumentException('OPUS_FSM_STATES_MISSING');
        }

        foreach ($states as $state) {
            if (!is_array($state)
                || !isset($state['id'])
                || !is_string($state['id'])
                || $state['id'] === '') {
                throw new InvalidArgumentException('OPUS_FSM_STATE_ID_INVALID');
            }
            if (isset($this->statesById[$state['id']])) {
                throw new InvalidArgumentException(
                    'OPUS_FSM_DUPLICATE_STATE: ' . $state['id']
                );
            }
            $this->statesById[$state['id']] = $state;
        }

        $initial = (string) ($this->fsm['initial_state'] ?? '');
        if ($initial === '' || !isset($this->statesById[$initial])) {
            throw new InvalidArgumentException(
                'OPUS_FSM_INITIAL_STATE_INVALID: ' . $initial
            );
        }

        $transitions = $this->fsm['transitions'] ?? null;
        if (!is_array($transitions)) {
            throw new InvalidArgumentException('OPUS_FSM_TRANSITIONS_MISSING');
        }

        $seen = [];
        foreach ($transitions as $transition) {
            if (!is_array($transition)) {
                throw new InvalidArgumentException('OPUS_FSM_TRANSITION_INVALID');
            }
            $from = (string) ($transition['from'] ?? '');
            $event = (string) ($transition['event'] ?? '');
            $to = (string) ($transition['to'] ?? '');
            if ($from === '' || $event === '' || $to === '') {
                throw new InvalidArgumentException(
                    'OPUS_FSM_TRANSITION_FIELDS_INVALID'
                );
            }
            if ($from !== '*' && !isset($this->statesById[$from])) {
                throw new InvalidArgumentException(
                    'OPUS_FSM_TRANSITION_SOURCE_UNKNOWN: ' . $from
                );
            }
            if (!isset($this->statesById[$to])) {
                throw new InvalidArgumentException(
                    'OPUS_FSM_TRANSITION_TARGET_UNKNOWN: ' . $to
                );
            }

            $signature = $from . ':' . $event;
            if (isset($seen[$signature])) {
                throw new InvalidArgumentException(
                    'OPUS_FSM_DUPLICATE_TRANSITION: ' . $signature
                );
            }
            $seen[$signature] = true;
        }
    }

    /** @return array<string,mixed>|null */
    private function findTransition(string $currentState, string $event): ?array
    {
        $stateAny = null;
        $globalExact = null;
        $globalAny = null;
        $default = null;
        foreach ($this->transitions() as $transition) {
            $from = (string) ($transition['from'] ?? '');
            $candidateEvent = (string) ($transition['event'] ?? '');
            if ($from === $currentState && $candidateEvent === $event) {
                return $transition;
            }
            if ($from === $currentState && $candidateEvent === '__any__') {
                $stateAny = $transition;
            } elseif ($from === '*' && $candidateEvent === $event) {
                $globalExact = $transition;
            } elseif ($from === '*' && $candidateEvent === '__any__') {
                $globalAny = $transition;
            } elseif ($candidateEvent === '__default__') {
                $default = $transition;
            }
        }
        return $stateAny ?? $globalExact ?? $globalAny ?? $default;
    }

    /**
     * @param array<string,mixed> $transition
     * @param array<string,mixed> $context
     */
    private function applyMemoryOperations(
        array $transition,
        array $context
    ): void {
        $operations = $transition['runtime_operations'] ?? [];
        if (!is_array($operations)) {
            throw new RuntimeException('OPUS_FSM_RUNTIME_OPERATIONS_INVALID');
        }
        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                throw new RuntimeException('OPUS_FSM_RUNTIME_OPERATION_INVALID');
            }
            $name = (string) ($operation['name'] ?? '');
            $contextKey = (string) ($operation['context'] ?? '');
            switch ((string) ($operation['op'] ?? '')) {
                case 'poke':
                    if ($contextKey === '' || !array_key_exists($contextKey, $context)) {
                        throw new RuntimeException(
                            'OPUS_FSM_RUNTIME_CONTEXT_MISSING: ' . $contextKey
                        );
                    }
                    $this->poke($name, $context[$contextKey]);
                    break;
                case 'push':
                    if ($contextKey === '' || !array_key_exists($contextKey, $context)) {
                        throw new RuntimeException(
                            'OPUS_FSM_RUNTIME_CONTEXT_MISSING: ' . $contextKey
                        );
                    }
                    $this->push($context[$contextKey]);
                    break;
                case 'pop':
                    $this->poke($name, $this->pop());
                    break;
                default:
                    throw new RuntimeException(
                        'OPUS_FSM_RUNTIME_OPERATION_UNKNOWN'
                    );
            }
        }
    }

    /** @param array<string,mixed> $transition @return list<string> */
    private function transitionGuards(array $transition): array
    {
        $guards = $transition['guards'] ?? ($transition['guard'] ?? []);
        if (is_string($guards) && $guards !== '') {
            return [$guards];
        }
        if (!is_array($guards)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $guard): string => is_string($guard)
                    ? $guard
                    : '',
                $guards
            ),
            static fn (string $guard): bool => $guard !== ''
        ));
    }

    /** @param array<string,mixed> $transition @return list<string> */
    private function transitionActions(array $transition): array
    {
        $actions = $transition['actions'] ?? ($transition['action'] ?? []);
        if (is_string($actions) && $actions !== '') {
            return [$actions];
        }
        if (!is_array($actions)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $action): string => is_string($action)
                    ? $action
                    : '',
                $actions
            ),
            static fn (string $action): bool => $action !== ''
        ));
    }

    /**
     * @param array<string,mixed> $transition
     * @param array<string,mixed> $context
     */
    private function evaluateGuard(
        string $guard,
        string $currentState,
        string $event,
        array $transition,
        array $context
    ): bool {
        if ($guard === 'always') {
            return true;
        }

        if (isset($this->guardHandlers[$guard])) {
            return (bool) ($this->guardHandlers[$guard])(
                $currentState,
                $event,
                $transition,
                $context,
                $this
            );
        }

        if ($guard === 'route_exists') {
            $target = (string) ($transition['to'] ?? '');
            return isset($this->statesById[$target])
                && (string) ($this->statesById[$target]['route'] ?? '') !== '';
        }

        if ($guard === 'app_exists') {
            return ($context['app_exists'] ?? null) === true
                || is_array($context['registry_entry'] ?? null)
                || (string) ($context['selected_app'] ?? '') !== '';
        }

        if ($guard === 'current_app_required') {
            $currentApp = $context['current_app'] ?? null;
            return ($context['has_current_app'] ?? null) === true
                || (is_array($currentApp) && $currentApp !== [])
                || (is_string($currentApp) && $currentApp !== '');
        }

        if ($guard === 'current_app_or_creation_request') {
            $currentApp = $context['current_app'] ?? null;
            $hasCurrentApp = ($context['has_current_app'] ?? null) === true
                || (is_array($currentApp) && $currentApp !== [])
                || (is_string($currentApp) && $currentApp !== '');
            return $hasCurrentApp
                || is_array($context['creation_request'] ?? null)
                || ($context['creation_request_started'] ?? null) === true;
        }

        if ($guard === 'must_change_password') {
            return ($context['must_change_password'] ?? null) === true;
        }

        throw new RuntimeException('OPUS_FSM_GUARD_UNSUPPORTED: ' . $guard);
    }
}
