<?php

declare(strict_types=1);


namespace ASAP\Fsm;

/**
 * PUBLIC DTO
 *
 * Role:
 *   Defines a valid FSM state.
 *
 * Responsibility:
 *   Carry a stable state identifier and optional label.
 *
 * Contract:
 *   State identifiers must be explicit non-empty strings.
 *
 * @package ASAP\Fsm
 /**
 * ASAP_REFBOOK:
 *   domain: FSM
 *   role: Immutable definition of one FSM state.
 *   contract:
 *     - describes state metadata only
 *     - does not execute transitions
 *     - is consumed by the StateMachine runtime
 *   examples:
 *     - fsm-definition
 *   diagrams:
 *     - fsm-runtime
 * END_ASAP_REFBOOK
 */
 */
final class StateDefinition
{
    private string $id;
    private string $label;

    /**
     * PUBLIC API
     *
     * @param string $id Stable state identifier.
     * @param string|null $label Optional human-readable label.
     *
     * @throws StateMachineException When the state identifier is empty.
     */
    public function __construct(string $id, ?string $label = null)
    {
        $id = trim($id);

        if ($id === '') {
            throw StateMachineException::contract(StateMachineException::STATE_UNKNOWN, 'State id must not be empty.');
        }

        $this->id = $id;
        $this->label = $label ?? $id;
    }

    /**
     * PUBLIC API
     *
     * @return string Stable state identifier.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * PUBLIC API
     *
     * @return string Human-readable state label.
     */
    public function label(): string
    {
        return $this->label;
    }
}
