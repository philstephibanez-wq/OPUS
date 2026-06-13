<?php

declare(strict_types=1);

namespace Opus\Fsm;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;
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
 * OPUS_REFBOOK:
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
 * END_OPUS_REFBOOK
 */
#[OpusRefBookClass(
    domain: 'FSM',
    role: 'Represent explicit FSM contract and runtime failures',
    responsibility: 'Carry stable FSM error codes and human-readable failure details without silent fallback.',
    contracts: [
        'Invalid FSM operations fail explicitly.',
        'Stable FSM error codes remain distinguishable from generic exceptions.',
        'The exception builder keeps the stable code as message prefix.',
    ],
    examples: ['fsm-error'],
    diagrams: ['fsm-runtime'],
    introducedIn: 'P112Q3E1'
)]
final class StateMachineException extends RuntimeException implements RefBookInspectableInterface
{
    public const SIGNAL_UNKNOWN = 'FSM_SIGNAL_UNKNOWN';
    public const STATE_UNKNOWN = 'FSM_STATE_UNKNOWN';
    public const TRANSITION_NOT_ALLOWED = 'FSM_TRANSITION_NOT_ALLOWED';
    public const ACTION_NOT_FOUND = 'FSM_ACTION_NOT_FOUND';
    public const MEMORY_CONTRACT_FAILED = 'FSM_MEMORY_CONTRACT_FAILED';
    public const TIMEOUT_REACHED = 'FSM_TIMEOUT_REACHED';
    public const SERIALIZATION_FORBIDDEN = 'FSM_SERIALIZATION_FORBIDDEN';
    public const CONTRACT_FAILED = 'FSM_CONTRACT_FAILED';

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for FSM exceptions',
        behavior: 'Returns the stable RefBook domain used by scanners, snapshots and OPUS_REF_BOOK renderers.',
        preconditions: ['none'],
        postconditions: ['The returned domain is FSM.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-refbook-domain'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public static function refBookDomain(): string
    {
        return 'FSM';
    }

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
    #[OpusRefBookMethod(
        role: 'Create an explicit FSM contract exception',
        behavior: 'Builds a StateMachineException whose message starts with a stable FSM error code followed by the failure detail.',
        preconditions: [
            'The code name is a stable FSM error code.',
            'The detail explains the human-readable failure cause.',
        ],
        postconditions: ['The returned exception message begins with the stable error code.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-error'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public static function contract(string $codeName, string $detail): self
    {
        return new self($codeName . ': ' . $detail);
    }
}
