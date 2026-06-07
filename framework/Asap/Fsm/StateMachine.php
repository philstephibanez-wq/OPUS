<?php

declare(strict_types=1);


namespace ASAP\Fsm;

/**
 * PUBLIC CLASS
 *
 * Role:
 *   Execute explicit FSM transitions.
 *
 * Responsibility:
 *   Own current state, validate signals and apply declared transitions.
 *
 * Contract:
 *   No fallback state. No implicit transition. No GraphViz dependency. No destructor persistence.
 *
 * @package ASAP\Fsm
 /**
 * ASAP_REFBOOK:
 *   domain: FSM
 *   role: Runtime executor that evaluates signals, guards and transitions.
 *   contract:
 *     - owns transition execution only
 *     - does not persist state outside the official memory contract
 *     - returns explicit transition results
 *   examples:
 *     - fsm-basic-transition
 *   diagrams:
 *     - fsm-runtime
 * END_ASAP_REFBOOK
 */
 */
final class StateMachine
{
    /** @var array<string,StateDefinition> */
    private array $states = [];

    /** @var array<string,TransitionDefinition> */
    private array $transitions = [];

    private string $currentState;
    private StateMemory $memory;

    /**
     * PUBLIC API
     *
     * @param StateDefinition[] $states Declared states.
     * @param TransitionDefinition[] $transitions Declared transitions.
     * @param string $initialState Initial state identifier.
     *
     * @throws StateMachineException When the initial state is not declared.
     */
    public function __construct(array $states, array $transitions, string $initialState)
    {
        foreach ($states as $state) {
            if (!$state instanceof StateDefinition) {
                throw StateMachineException::contract(StateMachineException::CONTRACT_FAILED, 'States must be StateDefinition instances.');
            }

            $this->states[$state->id()] = $state;
        }

        foreach ($transitions as $transition) {
            if (!$transition instanceof TransitionDefinition) {
                throw StateMachineException::contract(StateMachineException::CONTRACT_FAILED, 'Transitions must be TransitionDefinition instances.');
            }

            $this->transitions[$transition->key()] = $transition;
        }

        if (!isset($this->states[$initialState])) {
            throw StateMachineException::contract(StateMachineException::STATE_UNKNOWN, 'Initial state is not declared: ' . $initialState);
        }

        $this->currentState = $initialState;
        $this->memory = new StateMemory();
    }

    /**
     * PUBLIC API
     *
     * @return string Current state identifier.
     */
    public function currentState(): string
    {
        return $this->currentState;
    }

    /**
     * PUBLIC API
     *
     * @return StateMemory FSM memory object.
     */
    public function memory(): StateMemory
    {
        return $this->memory;
    }

    /**
     * PUBLIC API
     *
     * Role:
     *   Apply one signal to the current state.
     *
     * @param string $signal Signal identifier.
     *
     * @return TransitionResult Successful transition result.
     *
     * @throws StateMachineException When the signal is not allowed from current state.
     *
     * Side effects:
     *   Updates the current state and may execute a declared transition action.
     *
     * Contract:
     *   No implicit transition. Unknown signal/state fails explicitly.
     */
    public function apply(string $signal): TransitionResult
    {
        $key = $this->currentState . '::' . $signal;

        if (!isset($this->transitions[$key])) {
            throw StateMachineException::contract(
                StateMachineException::TRANSITION_NOT_ALLOWED,
                'No transition from ' . $this->currentState . ' with signal ' . $signal . '.'
            );
        }

        $transition = $this->transitions[$key];

        if (!isset($this->states[$transition->toState()])) {
            throw StateMachineException::contract(StateMachineException::STATE_UNKNOWN, 'Target state is not declared: ' . $transition->toState());
        }

        $from = $this->currentState;

        if ($transition->action() !== null) {
            $transition->action()->execute($transition, $this->memory);
        }

        $this->currentState = $transition->toState();

        return new TransitionResult($from, $signal, $this->currentState);
    }
}
