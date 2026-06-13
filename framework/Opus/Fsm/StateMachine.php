<?php

declare(strict_types=1);

namespace Opus\Fsm;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

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
 * OPUS_REFBOOK:
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
 * END_OPUS_REFBOOK
 */
#[OpusRefBookClass(
    domain: 'FSM',
    role: 'Execute explicit finite-state machine transitions',
    responsibility: 'Own the current state, validate incoming signals and apply only declared transitions.',
    contracts: [
        'No fallback state is created.',
        'No implicit transition is accepted.',
        'Transition actions are explicit StateActionInterface objects.',
        'The current state changes only after a declared transition has been validated.',
    ],
    examples: ['fsm-basic-transition'],
    diagrams: ['fsm-runtime'],
    introducedIn: 'P112Q3E1'
)]
final class StateMachine implements RefBookInspectableInterface
{
    /** @var array<string,StateDefinition> */
    private array $states = [];

    /** @var array<string,TransitionDefinition> */
    private array $transitions = [];

    private string $currentState;
    private StateMemory $memory;

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for the FSM runtime',
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
     * @param StateDefinition[] $states Declared states.
     * @param TransitionDefinition[] $transitions Declared transitions.
     * @param string $initialState Initial state identifier.
     *
     * @throws StateMachineException When the initial state is not declared.
     */
    #[OpusRefBookMethod(
        role: 'Create a state machine from declared states and transitions',
        behavior: 'Indexes state and transition definitions, validates the initial state and creates isolated FSM memory for this runtime instance.',
        preconditions: [
            'Every state entry is a StateDefinition instance.',
            'Every transition entry is a TransitionDefinition instance.',
            'The initial state identifier exists in the declared state set.',
        ],
        postconditions: [
            'The current state equals the validated initial state.',
            'The state machine owns a fresh StateMemory instance.',
            'Declared transitions are addressable by their stable key.',
        ],
        sideEffects: ['Initializes runtime state and memory for this object only.'],
        errors: ['FSM_CONTRACT_FAILED', 'FSM_STATE_UNKNOWN'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-basic-transition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
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
    #[OpusRefBookMethod(
        role: 'Read the current FSM state identifier',
        behavior: 'Returns the validated state identifier currently owned by this StateMachine instance.',
        preconditions: ['The StateMachine has been successfully constructed.'],
        postconditions: ['The returned value is one of the declared state identifiers.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-basic-transition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function currentState(): string
    {
        return $this->currentState;
    }

    /**
     * PUBLIC API
     *
     * @return StateMemory FSM memory object.
     */
    #[OpusRefBookMethod(
        role: 'Expose the FSM memory object for declared actions',
        behavior: 'Returns the controlled memory container owned by this StateMachine instance.',
        preconditions: ['The StateMachine has been successfully constructed.'],
        postconditions: ['The same StateMemory instance is returned for this runtime object.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-action'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
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
    #[OpusRefBookMethod(
        role: 'Apply one signal to the current state',
        behavior: 'Looks up the declared transition for current state and signal, executes its optional action, updates the current state and returns a TransitionResult.',
        preconditions: [
            'The signal is a declared transition signal for the current state.',
            'The target state exists in the declared state set.',
        ],
        postconditions: [
            'The current state equals the transition target state.',
            'The returned TransitionResult reports previous state, signal and next state.',
            'No state is changed when the transition is not allowed.',
        ],
        sideEffects: [
            'Mutates the current state on success.',
            'May execute the declared StateActionInterface action.',
        ],
        errors: ['FSM_TRANSITION_NOT_ALLOWED', 'FSM_STATE_UNKNOWN'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-basic-transition'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
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
