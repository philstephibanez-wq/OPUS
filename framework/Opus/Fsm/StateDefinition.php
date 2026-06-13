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
 *   Defines a valid FSM state.
 *
 * Responsibility:
 *   Carry a stable state identifier and optional label.
 *
 * Contract:
 *   State identifiers must be explicit non-empty strings.
 *
 * @package ASAP\Fsm
 * OPUS_REFBOOK:
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
 * END_OPUS_REFBOOK
 */
#[OpusRefBookClass(
    domain: 'FSM',
    role: 'Define one explicit finite-state machine state',
    responsibility: 'Carry a stable state identifier and human-readable label used by StateMachine definitions.',
    contracts: [
        'The state identifier is non-empty after trimming.',
        'The label never participates in transition selection.',
        'The object is immutable after construction.',
    ],
    examples: ['fsm-definition'],
    diagrams: ['fsm-runtime'],
    introducedIn: 'P112Q3E1'
)]
final class StateDefinition implements RefBookInspectableInterface
{
    private string $id;
    private string $label;

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for FSM state definitions',
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
     * @param string $id Stable state identifier.
     * @param string|null $label Optional human-readable label.
     *
     * @throws StateMachineException When the state identifier is empty.
     */
    #[OpusRefBookMethod(
        role: 'Create an immutable FSM state definition',
        behavior: 'Normalizes the state identifier, validates that it is not empty and stores a label for human-readable documentation.',
        preconditions: ['The provided state identifier must not be empty after trimming.'],
        postconditions: [
            'The state identifier is stable and non-empty.',
            'The label equals the provided label or the state identifier when no label is provided.',
        ],
        sideEffects: ['none'],
        errors: ['FSM_STATE_UNKNOWN'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-definition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
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
    #[OpusRefBookMethod(
        role: 'Read the stable FSM state identifier',
        behavior: 'Returns the normalized identifier used by TransitionDefinition and StateMachine transition lookup.',
        preconditions: ['The StateDefinition has been successfully constructed.'],
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

    /**
     * PUBLIC API
     *
     * @return string Human-readable state label.
     */
    #[OpusRefBookMethod(
        role: 'Read the human-readable FSM state label',
        behavior: 'Returns the label used for documentation, diagnostics or UI display without changing transition semantics.',
        preconditions: ['The StateDefinition has been successfully constructed.'],
        postconditions: ['The returned label is stable for this object.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-definition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function label(): string
    {
        return $this->label;
    }
}
