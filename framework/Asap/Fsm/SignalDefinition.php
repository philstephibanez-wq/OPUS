<?php

declare(strict_types=1);


namespace ASAP\Fsm;

/**
 * PUBLIC DTO
 *
 * Role:
 *   Defines a valid FSM signal.
 *
 * Responsibility:
 *   Carry a stable signal identifier.
 *
 * Contract:
 *   Signal identifiers must be explicit non-empty strings.
 *
 * @package ASAP\Fsm
 /**
 * ASAP_REFBOOK:
 *   domain: FSM
 *   role: Immutable definition of a signal accepted by an FSM.
 *   contract:
 *     - describes signal identity and metadata only
 *     - does not mutate state
 *     - is validated before transition execution
 *   examples:
 *     - fsm-definition
 *   diagrams:
 *     - fsm-runtime
 * END_ASAP_REFBOOK
 */
 */
final class SignalDefinition
{
    private string $id;

    /**
     * PUBLIC API
     *
     * @param string $id Stable signal identifier.
     *
     * @throws StateMachineException When the signal identifier is empty.
     */
    public function __construct(string $id)
    {
        $id = trim($id);

        if ($id === '') {
            throw StateMachineException::contract(StateMachineException::SIGNAL_UNKNOWN, 'Signal id must not be empty.');
        }

        $this->id = $id;
    }

    /**
     * PUBLIC API
     *
     * @return string Stable signal identifier.
     */
    public function id(): string
    {
        return $this->id;
    }
}
