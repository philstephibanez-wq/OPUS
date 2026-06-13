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
 *   Represents the result of a successful FSM transition.
 *
 * Responsibility:
 *   Carry previous state, signal and next state after validation.
 *
 * Contract:
 *   A TransitionResult exists only for a successful transition.
 *
 * @package ASAP\Fsm
 * OPUS_REFBOOK:
 *   domain: FSM
 *   role: Structured result returned after transition evaluation.
 *   contract:
 *     - reports transition outcome explicitly
 *     - contains no hidden fallback state
 *     - is safe for controller/template consumption
 *   examples:
 *     - fsm-basic-transition
 *   diagrams:
 *     - fsm-runtime
 * END_OPUS_REFBOOK
 */
#[OpusRefBookClass(
    domain: 'FSM',
    role: 'Represent a successful FSM transition outcome',
    responsibility: 'Carry previous state, applied signal and next state after StateMachine validation.',
    contracts: [
        'A TransitionResult is created only for a successful transition.',
        'It contains no hidden fallback state.',
        'It is immutable after construction.',
    ],
    examples: ['fsm-basic-transition'],
    diagrams: ['fsm-runtime'],
    introducedIn: 'P112Q3E1'
)]
final class TransitionResult implements RefBookInspectableInterface
{
    private string $fromState;
    private string $signal;
    private string $toState;

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for FSM transition results',
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
     * @param string $fromState Previous state identifier.
     * @param string $signal Signal identifier.
     * @param string $toState Next state identifier.
     */
    #[OpusRefBookMethod(
        role: 'Create an immutable FSM transition result',
        behavior: 'Stores previous state, applied signal and next state after a successful StateMachine transition.',
        preconditions: ['The caller has already validated and applied the transition.'],
        postconditions: ['The result exposes transition outcome values without mutating the FSM.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-basic-transition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function __construct(string $fromState, string $signal, string $toState)
    {
        $this->fromState = $fromState;
        $this->signal = $signal;
        $this->toState = $toState;
    }

    /**
     * PUBLIC API
     *
     * @return string Previous state identifier.
     */
    #[OpusRefBookMethod(
        role: 'Read the previous FSM state identifier',
        behavior: 'Returns the state from which the successful transition started.',
        preconditions: ['The TransitionResult has been successfully constructed.'],
        postconditions: ['The returned value is stable for this result object.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-basic-transition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function fromState(): string
    {
        return $this->fromState;
    }

    /**
     * PUBLIC API
     *
     * @return string Signal identifier.
     */
    #[OpusRefBookMethod(
        role: 'Read the applied FSM signal identifier',
        behavior: 'Returns the signal that triggered the successful transition.',
        preconditions: ['The TransitionResult has been successfully constructed.'],
        postconditions: ['The returned value is stable for this result object.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-basic-transition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function signal(): string
    {
        return $this->signal;
    }

    /**
     * PUBLIC API
     *
     * @return string Next state identifier.
     */
    #[OpusRefBookMethod(
        role: 'Read the next FSM state identifier',
        behavior: 'Returns the state reached by the successful transition.',
        preconditions: ['The TransitionResult has been successfully constructed.'],
        postconditions: ['The returned value is stable for this result object.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-basic-transition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function toState(): string
    {
        return $this->toState;
    }
}
