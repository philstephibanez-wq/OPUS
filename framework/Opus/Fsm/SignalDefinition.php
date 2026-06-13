<?php

declare(strict_types=1);

namespace Opus\Fsm;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

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
 * OPUS_REFBOOK:
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
 * END_OPUS_REFBOOK
 */
#[OpusRefBookClass(
    domain: 'FSM',
    role: 'Define one explicit finite-state machine signal',
    responsibility: 'Carry a stable signal identifier used to select declared transitions from the current FSM state.',
    contracts: [
        'The signal identifier is non-empty after trimming.',
        'The signal definition does not mutate state.',
        'The object is immutable after construction.',
    ],
    examples: ['fsm-definition'],
    diagrams: ['fsm-runtime'],
    introducedIn: 'P112Q3E1'
)]
final class SignalDefinition implements RefBookInspectableInterface
{
    private string $id;

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for FSM signal definitions',
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
     * @param string $id Stable signal identifier.
     *
     * @throws StateMachineException When the signal identifier is empty.
     */
    #[OpusRefBookMethod(
        role: 'Create an immutable FSM signal definition',
        behavior: 'Trims and validates a signal identifier before storing it as explicit transition-selection metadata.',
        preconditions: ['The provided signal identifier is a string.'],
        postconditions: [
            'The stored signal identifier is non-empty.',
            'The object remains immutable after construction.',
        ],
        sideEffects: ['Initializes this value object only.'],
        errors: ['FSM_SIGNAL_UNKNOWN'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-definition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
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
    #[OpusRefBookMethod(
        role: 'Read the stable FSM signal identifier',
        behavior: 'Returns the validated signal identifier carried by this immutable definition.',
        preconditions: ['The SignalDefinition has been successfully constructed.'],
        postconditions: ['The returned identifier is non-empty.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-definition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function id(): string
    {
        return $this->id;
    }
}
