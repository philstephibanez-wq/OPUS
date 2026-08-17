<?php
declare(strict_types=1);

namespace Opus\Fsm;

use InvalidArgumentException;
use Opus\File\StructuredFileLoader;
use Opus\Profiler\ProfilerInterface;
use RuntimeException;

/**
 * Executes OPUS finite state machines from versioned configuration arrays.
 *
 * Ordinary global transitions are finite relations declared with
 * scope="global" + from_states=[...]. They are distinct from NMI, never use
 * the wildcard source, and are considered only after an exact state-local
 * transition. NMI remains the sole preemptive wildcard relation.
 */
final class FsmProcessor implements FsmProcessorInterface
{
    private const RESULT_CONTRACT = 'OPUS_FSM_PROCESSOR_RESULT_V2';

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
    public function __construct(
        array $fsm,
        array $guardHandlers = [],
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null
    ) {
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
        array $guardHandlers = [],
        ?ProfilerInterface $profiler = null,
        ?string $parentSpanId = null
    ): self {
        try {
            $decoded = StructuredFileLoader::instance()->read($path);
        } catch (\Throwable $cause) {
            throw new RuntimeException('OPUS_FSM_JSON_INVALID: ' . $path, 0, $cause);
        }

        return new self($decoded, $guardHandlers, $profiler, $parentSpanId);
    }

    public function contract(): string
    {
        return (string) $this->fsm['contract'];
    }

    public function name(): string
    {
        return (string) $this->fsm['name'];
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
     * Executes a transition for a current state and signal.
     *
     * @param array<string,mixed> $context Runtime facts available to guards.
     * @return array<string,mixed>
     */
    public function transition(
        string $currentState,
        string $signal,
        array $context = []
    ): array {
        if ($currentState === '' || !isset($this->statesById[$currentState])) {
            throw new RuntimeException(
                'OPUS_FSM_CURRENT_STATE_UNKNOWN: ' . $currentState
            );
        }
        if ($signal === '') {
            throw new RuntimeException('OPUS_FSM_SIGNAL_REQUIRED');
        }

        $startedAt = microtime(true);
        $spanId = $this->beginTransitionSpan($currentState, $signal);
        $nextState = null;

        try {
            $this->currentState = $currentState;
            $transition = $this->findTransition($currentState, $signal);
            if ($transition === null) {
                throw new RuntimeException(
                    'OPUS_FSM_TRANSITION_NOT_FOUND: '
                    . $currentState . ':' . $signal
                );
            }

            $target = (string) ($transition['next_state'] ?? '');
            $nextState = $target;
            if ($target === '' || !isset($this->statesById[$target])) {
                throw new RuntimeException(
                    'OPUS_FSM_TARGET_STATE_UNKNOWN: ' . $target
                );
            }

            $transitionId = (string) ($transition['id'] ?? '');
            foreach ($this->transitionGuards($transition) as $guard) {
                $allowed = $this->evaluateGuard(
                    $guard,
                    $currentState,
                    $signal,
                    $transition,
                    $context
                );
                $this->profileEvent('guard.evaluated', [
                    'table_fsm' => $this->name(),
                    'current_state' => $currentState,
                    'signal' => $signal,
                    'next_state' => $target,
                    'transition_id' => $transitionId,
                    'guard' => $guard,
                    'result' => $allowed ? 'allowed' : 'denied',
                ], $allowed ? 'success' : 'error', $spanId);
                if (!$allowed) {
                    throw new RuntimeException('OPUS_FSM_GUARD_FAILED: ' . $guard);
                }
            }

            $actions = $this->transitionActions($transition);
            $this->currentState = $target;
            $this->applyMemoryOperations($transition, $context);
            $completed = [
                'table_fsm' => $this->name(),
                'transition_id' => $transitionId,
                'current_state' => $currentState,
                'signal' => $signal,
                'next_state' => $target,
                'scope' => $this->transitionScope($transition),
                'guards' => $this->transitionGuards($transition),
                'actions' => $actions,
                'duration_ms' => $this->durationMs($startedAt),
            ];
            $this->profileEvent(
                'transition.completed',
                $completed,
                'success',
                $spanId
            );
            $this->endTransitionSpan($spanId, 'success', $completed);

            return [
                'contract' => self::RESULT_CONTRACT,
                'table_fsm' => $this->name(),
                'current_state' => $currentState,
                'signal' => $signal,
                'next_state' => $target,
                'transition_id' => $transitionId,
                'scope' => $this->transitionScope($transition),
                'guards' => $this->transitionGuards($transition),
                'actions' => $actions,
                'action' => $actions[0] ?? '',
                'target_state' => $this->statesById[$target],
                'runtime' => $this->snapshot(),
            ];
        } catch (\Throwable $error) {
            $failureReason = str_starts_with(
                $error->getMessage(),
                'OPUS_FSM_TRANSITION_NOT_FOUND:'
            ) ? 'transition_not_found' : (
                str_starts_with($error->getMessage(), 'OPUS_FSM_GUARD_FAILED:')
                    ? 'guard_refused'
                    : 'transition_failed'
            );
            $failed = [
                'table_fsm' => $this->name(),
                'current_state' => $currentState,
                'signal' => $signal,
                'next_state' => $nextState,
                'failure_reason' => $failureReason,
                'duration_ms' => $this->durationMs($startedAt),
                'exception_class' => $error::class,
            ];
            $this->profileEvent(
                'transition.failed',
                $failed,
                'error',
                $spanId
            );
            $this->endTransitionSpan($spanId, 'error', $failed);
            throw $error;
        }
    }

    /** @return list<array<string,mixed>> */
    public function transitions(): array
    {
        return array_values($this->fsm['transitions']);
    }

    private function beginTransitionSpan(
        string $currentState,
        string $signal
    ): ?string {
        if ($this->profiler?->getActiveTrace() === null) {
            return null;
        }
        $context = [
            'table_fsm' => $this->name(),
            'current_state' => $currentState,
            'signal' => $signal,
        ];

        return $this->profiler->beginSpan(
            'fsm',
            'transition',
            $context,
            $this->parentSpanId
        );
    }

    /** @param array<string,mixed> $context */
    private function profileEvent(
        string $name,
        array $context,
        string $status,
        ?string $spanId
    ): void {
        if ($spanId === null || $this->profiler?->getActiveTrace() === null) {
            return;
        }
        $this->profiler->event(
            'fsm',
            $name,
            $context,
            $status,
            $spanId,
            $this->parentSpanId
        );
    }

    /** @param array<string,mixed> $context */
    private function endTransitionSpan(
        ?string $spanId,
        string $status,
        array $context
    ): void {
        if ($spanId === null || $this->profiler?->getActiveTrace() === null) {
            return;
        }
        $this->profiler->endSpan($spanId, $status, $context);
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 3);
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

        $name = trim((string) ($this->fsm['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('OPUS_FSM_NAME_REQUIRED');
        }

        $states = $this->fsm['states'] ?? null;
        if (!is_array($states) || $states === []) {
            throw new InvalidArgumentException('OPUS_FSM_STATES_MISSING');
        }

        foreach ($states as $state) {
            if (!is_array($state)
                || !isset($state['id'])
                || !is_string($state['id'])
                || $state['id'] === ''
                || $state['id'] === '*') {
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

        $seenIds = [];
        $seenLocal = [];
        $globalSourcesBySignal = [];
        $nmiSignals = [];

        foreach ($transitions as $transition) {
            if (!is_array($transition)) {
                throw new InvalidArgumentException('OPUS_FSM_TRANSITION_INVALID');
            }

            $id = trim((string) ($transition['id'] ?? ''));
            $from = trim((string) ($transition['from'] ?? ''));
            $signal = trim((string) ($transition['signal'] ?? ''));
            $to = trim((string) ($transition['next_state'] ?? ''));
            $interrupt = trim((string) ($transition['interrupt'] ?? ''));
            $scope = trim((string) ($transition['scope'] ?? ''));

            if ($id === '' || isset($seenIds[$id])) {
                throw new InvalidArgumentException(
                    'OPUS_FSM_TRANSITION_ID_INVALID: ' . $id
                );
            }
            $seenIds[$id] = true;

            if ($signal === '' || $to === '') {
                throw new InvalidArgumentException(
                    'OPUS_FSM_TRANSITION_FIELDS_INVALID'
                );
            }
            if (!isset($this->statesById[$to])) {
                throw new InvalidArgumentException(
                    'OPUS_FSM_TRANSITION_TARGET_UNKNOWN: ' . $to
                );
            }
            if ($interrupt !== '' && $interrupt !== 'nmi') {
                throw new InvalidArgumentException(
                    'OPUS_FSM_INTERRUPT_INVALID: ' . $interrupt
                );
            }
            if ($scope !== '' && $scope !== 'global') {
                throw new InvalidArgumentException(
                    'OPUS_FSM_TRANSITION_SCOPE_INVALID: ' . $scope
                );
            }

            if ($interrupt === 'nmi') {
                if ($scope !== '' || $from !== '*') {
                    throw new InvalidArgumentException(
                        'OPUS_FSM_NMI_SOURCE_INVALID: ' . $from
                    );
                }
                if (($transition['from_states'] ?? null) !== null) {
                    throw new InvalidArgumentException(
                        'OPUS_FSM_NMI_FINITE_SOURCES_FORBIDDEN: ' . $signal
                    );
                }
                if (in_array($signal, ['__any__', '__default__'], true)) {
                    throw new InvalidArgumentException(
                        'OPUS_FSM_NMI_SIGNAL_MUST_BE_EXPLICIT: ' . $signal
                    );
                }
                if ($this->transitionGuards($transition) !== []) {
                    throw new InvalidArgumentException(
                        'OPUS_FSM_NMI_GUARD_FORBIDDEN: ' . $signal
                    );
                }
                if (isset($nmiSignals[$signal])) {
                    throw new InvalidArgumentException(
                        'OPUS_FSM_DUPLICATE_TRANSITION: nmi:*:' . $signal
                    );
                }
                $nmiSignals[$signal] = true;
                continue;
            }

            if ($scope === 'global') {
                if ($from !== '' || $from === '*') {
                    throw new InvalidArgumentException(
                        'OPUS_FSM_GLOBAL_SOURCE_MUST_BE_FINITE: ' . $signal
                    );
                }
                if (in_array($signal, ['__any__', '__default__'], true)) {
                    throw new InvalidArgumentException(
                        'OPUS_FSM_GLOBAL_SIGNAL_MUST_BE_EXPLICIT: ' . $signal
                    );
                }
                $sources = $this->finiteGlobalSources($transition);
                foreach ($sources as $source) {
                    if (!isset($this->statesById[$source])) {
                        throw new InvalidArgumentException(
                            'OPUS_FSM_GLOBAL_SOURCE_UNKNOWN: '
                            . $signal . ':' . $source
                        );
                    }
                    if (isset($globalSourcesBySignal[$signal][$source])) {
                        throw new InvalidArgumentException(
                            'OPUS_FSM_GLOBAL_SOURCE_AMBIGUOUS: '
                            . $signal . ':' . $source
                        );
                    }
                    $globalSourcesBySignal[$signal][$source] = true;
                }
                continue;
            }

            if ($from === '' || $from === '*') {
                throw new InvalidArgumentException(
                    'OPUS_FSM_GLOBAL_SOURCE_FORBIDDEN: ' . $signal
                );
            }
            if (($transition['from_states'] ?? null) !== null) {
                throw new InvalidArgumentException(
                    'OPUS_FSM_LOCAL_FINITE_SOURCES_FORBIDDEN: ' . $signal
                );
            }
            if (!isset($this->statesById[$from])) {
                throw new InvalidArgumentException(
                    'OPUS_FSM_TRANSITION_SOURCE_UNKNOWN: ' . $from
                );
            }

            $signature = $from . ':' . $signal;
            if (isset($seenLocal[$signature])) {
                throw new InvalidArgumentException(
                    'OPUS_FSM_DUPLICATE_TRANSITION: ' . $signature
                );
            }
            $seenLocal[$signature] = true;
        }
    }

    /** @return array<string,mixed>|null */
    private function findTransition(string $currentState, string $signal): ?array
    {
        /* NMI remains preemptive and is the sole wildcard source. */
        foreach ($this->transitions() as $transition) {
            if (($transition['interrupt'] ?? null) !== 'nmi') {
                continue;
            }
            if ((string) ($transition['signal'] ?? '') === $signal) {
                return $transition;
            }
        }

        $stateAny = null;
        $stateDefault = null;

        /* Exact state-local relation always wins over ordinary global. */
        foreach ($this->transitions() as $transition) {
            if ($this->transitionScope($transition) !== 'local') {
                continue;
            }
            if ((string) ($transition['from'] ?? '') !== $currentState) {
                continue;
            }

            $candidateSignal = (string) ($transition['signal'] ?? '');
            if ($candidateSignal === $signal) {
                return $transition;
            }
            if ($candidateSignal === '__any__') {
                $stateAny = $transition;
            } elseif ($candidateSignal === '__default__') {
                $stateDefault = $transition;
            }
        }

        /* Ordinary global transitions are explicit finite relations. */
        foreach ($this->transitions() as $transition) {
            if ($this->transitionScope($transition) !== 'global') {
                continue;
            }
            if ((string) ($transition['signal'] ?? '') !== $signal) {
                continue;
            }
            if (in_array($currentState, $this->finiteGlobalSources($transition), true)) {
                return $transition;
            }
        }

        return $stateAny ?? $stateDefault;
    }

    /** @param array<string,mixed> $transition */
    private function transitionScope(array $transition): string
    {
        if (($transition['interrupt'] ?? null) === 'nmi') {
            return 'nmi';
        }
        return ($transition['scope'] ?? null) === 'global'
            ? 'global'
            : 'local';
    }

    /**
     * @param array<string,mixed> $transition
     * @return list<string>
     */
    private function finiteGlobalSources(array $transition): array
    {
        $sources = $transition['from_states'] ?? null;
        if (!is_array($sources) || $sources === []) {
            throw new InvalidArgumentException(
                'OPUS_FSM_GLOBAL_SOURCES_MISSING: '
                . (string) ($transition['signal'] ?? '')
            );
        }

        $result = [];
        foreach ($sources as $source) {
            if (!is_string($source) || trim($source) === '' || $source === '*') {
                throw new InvalidArgumentException(
                    'OPUS_FSM_GLOBAL_SOURCE_INVALID: '
                    . (string) ($transition['signal'] ?? '')
                );
            }
            $source = trim($source);
            if (isset($result[$source])) {
                throw new InvalidArgumentException(
                    'OPUS_FSM_GLOBAL_SOURCE_DUPLICATE: '
                    . (string) ($transition['signal'] ?? '') . ':' . $source
                );
            }
            $result[$source] = true;
        }

        return array_keys($result);
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
        string $signal,
        array $transition,
        array $context
    ): bool {
        if ($guard === 'always') {
            return true;
        }

        if (isset($this->guardHandlers[$guard])) {
            return (bool) ($this->guardHandlers[$guard])(
                $currentState,
                $signal,
                $transition,
                $context,
                $this
            );
        }

        if ($guard === 'route_exists') {
            $target = (string) ($transition['next_state'] ?? '');
            return isset($this->statesById[$target])
                && (string) ($this->statesById[$target]['route'] ?? '') !== '';
        }

        if ($guard === 'app_exists') {
            return ($context['app_exists'] ?? null) === true
                || is_array($context['registry_entry'] ?? null)
                || is_array($context['selected_app'] ?? null)
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
