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
 *   Defines one allowed FSM transition.
 *
 * Responsibility:
 *   Bind current state + signal to next state and optional action.
 *
 * Contract:
 *   A transition is valid only when all identifiers are explicit.
 *
 * @package ASAP\Fsm
 * OPUS_REFBOOK:
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
 * END_OPUS_REFBOOK
 */
#[OpusRefBookClass(
    domain: 'FSM',
    role: 'Define one declared finite-state machine transition',
    responsibility: 'Bind source state, signal, target state and optional action into an immutable transition definition.',
    contracts: [
        'Source state, signal and target state are explicit non-empty identifiers.',
        'The transition definition does not execute side effects by itself.',
        'The transition key is derived only from source state and signal.',
    ],
    examples: ['fsm-basic-transition'],
    diagrams: ['fsm-runtime'],
    introducedIn: 'P112Q3E1'
)]
final class TransitionDefinition implements RefBookInspectableInterface
{
    private string $fromState;
    private string $signal;
    private string $toState;
    private ?StateActionInterface $action;

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for FSM transition definitions',
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
     * @param string $fromState Current state identifier.
     * @param string $signal Signal identifier.
     * @param string $toState Next state identifier.
     * @param StateActionInterface|null $action Optional declared transition action.
     *
     * @throws StateMachineException When one identifier is empty.
     */
    #[OpusRefBookMethod(
        role: 'Create an immutable FSM transition definition',
        behavior: 'Normalizes and validates source state, signal and target state, then stores an optional explicit action object.',
        preconditions: [
            'Source state identifier is non-empty after trimming.',
            'Signal identifier is non-empty after trimming.',
            'Target state identifier is non-empty after trimming.',
        ],
        postconditions: [
            'The transition can be selected by StateMachine using its stable key.',
            'The optional action is stored without being executed.',
        ],
        sideEffects: ['none'],
        errors: ['FSM_CONTRACT_FAILED'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-basic-transition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
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
    #[OpusRefBookMethod(
        role: 'Read the source FSM state identifier',
        behavior: 'Returns the state identifier from which this transition may be selected.',
        preconditions: ['The TransitionDefinition has been successfully constructed.'],
        postconditions: ['The returned identifier is non-empty.'],
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
        role: 'Read the FSM transition signal identifier',
        behavior: 'Returns the signal that must be applied while the StateMachine is in the source state.',
        preconditions: ['The TransitionDefinition has been successfully constructed.'],
        postconditions: ['The returned signal is non-empty.'],
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
        role: 'Read the target FSM state identifier',
        behavior: 'Returns the state identifier reached when this transition is successfully applied.',
        preconditions: ['The TransitionDefinition has been successfully constructed.'],
        postconditions: ['The returned identifier is non-empty.'],
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

    /**
     * PUBLIC API
     *
     * @return StateActionInterface|null Declared transition action.
     */
    #[OpusRefBookMethod(
        role: 'Read the optional FSM transition action',
        behavior: 'Returns the explicit action object that StateMachine may execute after validating this transition.',
        preconditions: ['The TransitionDefinition has been successfully constructed.'],
        postconditions: ['The returned value is either null or a StateActionInterface instance.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-action'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function action(): ?StateActionInterface
    {
        return $this->action;
    }

    /**
     * PUBLIC API
     *
     * @return string Stable transition key.
     */
    #[OpusRefBookMethod(
        role: 'Build the stable FSM transition lookup key',
        behavior: 'Returns the key used by StateMachine to find the transition matching current state and signal.',
        preconditions: ['The TransitionDefinition has been successfully constructed.'],
        postconditions: ['The returned key is source state, separator and signal.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-basic-transition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function key(): string
    {
        return $this->fromState . '::' . $this->signal;
    }
}
