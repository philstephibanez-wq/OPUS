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
 * Runtime storage/history is intentionally outside this class; callers pass the
 * current state and receive the next state/action result.
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

    /**
     * @param array<string,mixed> $fsm
     * @param array<string,callable> $guardHandlers
     */
    public function __construct(array $fsm, array $guardHandlers = [])
    {
        $this->guardHandlers = $guardHandlers;
        $this->fsm = $fsm;
        $this->validateFsm();
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
        $wildcard = null;
        foreach ($this->transitions() as $transition) {
            if (($transition['event'] ?? null) !== $event) {
                continue;
            }
            if (($transition['from'] ?? null) === $currentState) {
                return $transition;
            }
            if (($transition['from'] ?? null) === '*') {
                $wildcard = $transition;
            }
        }
        return $wildcard;
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
