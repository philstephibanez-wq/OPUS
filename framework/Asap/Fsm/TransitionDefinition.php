<?php

declare(strict_types=1);


namespace ASAP\Fsm;

/**
 * PUBLIC DTO
 *
 * Role:
 *   Defines one allowed FSM transition.
 *
 * Responsibility:
 *   Bind current state + signal to next state and optional action.
 *
 * Contract:
 *   A transition is valid only when all identifiers are explicit.
 *
 * @package ASAP\Fsm
 /**
 * ASAP_REFBOOK:
 *   domain: FSM
 *   role: Immutable definition of a transition between two states.
 *   contract:
 *     - declares source state, signal and target state
 *     - does not execute side effects directly
 *     - is selected by the StateMachine runtime
 *   examples:
 *     - fsm-basic-transition
 *   diagrams:
 *     - fsm-runtime
 * END_ASAP_REFBOOK
 */
 */
final class TransitionDefinition
{
    private string $fromState;
    private string $signal;
    private string $toState;
    private ?StateActionInterface $action;

    /**
     * PUBLIC API
     *
     * @param string $fromState Current state identifier.
     * @param string $signal Signal identifier.
     * @param string $toState Next state identifier.
     * @param StateActionInterface|null $action Optional declared transition action.
     *
     * @throws StateMachineException When one identifier is empty.
     */
    public function __construct(string $fromState, string $signal, string $toState, ?StateActionInterface $action = null)
    {
        $fromState = trim($fromState);
        $signal = trim($signal);
        $toState = trim($toState);

        if ($fromState === '' || $signal === '' || $toState === '') {
            throw StateMachineException::contract(StateMachineException::CONTRACT_FAILED, 'Transition identifiers must not be empty.');
        }

        $this->fromState = $fromState;
        $this->signal = $signal;
        $this->toState = $toState;
        $this->action = $action;
    }

    /**
     * PUBLIC API
     *
     * @return string Current state identifier.
     */
    public function fromState(): string
    {
        return $this->fromState;
    }

    /**
     * PUBLIC API
     *
     * @return string Signal identifier.
     */
    public function signal(): string
    {
        return $this->signal;
    }

    /**
     * PUBLIC API
     *
     * @return string Next state identifier.
     */
    public function toState(): string
    {
        return $this->toState;
    }

    /**
     * PUBLIC API
     *
     * @return StateActionInterface|null Declared transition action.
     */
    public function action(): ?StateActionInterface
    {
        return $this->action;
    }

    /**
     * PUBLIC API
     *
     * @return string Stable transition key.
     */
    public function key(): string
    {
        return $this->fromState . '::' . $this->signal;
    }
}
