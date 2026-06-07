<?php

declare(strict_types=1);


namespace ASAP\Fsm;

use RuntimeException;

/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represents an explicit FSM contract failure.
 *
 * Responsibility:
 *   Carry a stable FSM error code and message when a state machine contract is violated.
 *
 * Contract:
 *   No silent fallback. Every invalid FSM operation must fail with an explicit code.
 *
 * @package ASAP\Fsm
 /**
 * ASAP_REFBOOK:
 *   domain: FSM
 *   role: Explicit exception type for FSM contract and runtime failures.
 *   contract:
 *     - signals FSM errors without fallback
 *     - keeps FSM failures distinguishable from generic exceptions
 *     - must not be swallowed by the RefBook extractor
 *   examples:
 *     - fsm-error
 *   diagrams:
 *     - fsm-runtime
 * END_ASAP_REFBOOK
 */
 */
final class StateMachineException extends RuntimeException
{
    public const SIGNAL_UNKNOWN = 'FSM_SIGNAL_UNKNOWN';
    public const STATE_UNKNOWN = 'FSM_STATE_UNKNOWN';
    public const TRANSITION_NOT_ALLOWED = 'FSM_TRANSITION_NOT_ALLOWED';
    public const ACTION_NOT_FOUND = 'FSM_ACTION_NOT_FOUND';
    public const MEMORY_CONTRACT_FAILED = 'FSM_MEMORY_CONTRACT_FAILED';
    public const TIMEOUT_REACHED = 'FSM_TIMEOUT_REACHED';
    public const SERIALIZATION_FORBIDDEN = 'FSM_SERIALIZATION_FORBIDDEN';
    public const CONTRACT_FAILED = 'FSM_CONTRACT_FAILED';

    /**
     * PUBLIC API
     *
     * Role:
     *   Create a FSM exception from a stable contract code.
     *
     * @param string $codeName Stable FSM error code.
     * @param string $detail Human-readable failure detail.
     *
     * @return self
     *
     * Side effects:
     *   None.
     *
     * Contract:
     *   The returned exception must keep the stable error code as message prefix.
     */
    public static function contract(string $codeName, string $detail): self
    {
        return new self($codeName . ': ' . $detail);
    }
}
