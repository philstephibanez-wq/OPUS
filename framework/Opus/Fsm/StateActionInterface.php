<?php

declare(strict_types=1);

namespace Opus\Fsm;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;

/**
 * PUBLIC INTERFACE
 *
 * Role:
 *   Defines a declared FSM transition action.
 *
 * Responsibility:
 *   Execute a transition side effect explicitly bound to a transition.
 *
 * Contract:
 *   Actions are declared objects. No arbitrary function name fallback is allowed.
 *
 * @package ASAP\Fsm
 * OPUS_REFBOOK:
 *   domain: FSM
 *   role: Contract for state or transition actions executed by the FSM runtime.
 *   contract:
 *     - defines the callable boundary for actions
 *     - does not own transition selection
 *     - must expose explicit action failures
 *   examples:
 *     - fsm-action
 *   diagrams:
 *     - fsm-runtime
 * END_OPUS_REFBOOK
 */
#[OpusRefBookClass(
    domain: 'FSM',
    role: 'Define the callable boundary for FSM transition actions',
    responsibility: 'Constrain transition side effects to explicit objects executed by StateMachine after transition validation.',
    contracts: [
        'Actions do not select transitions.',
        'Actions receive the validated transition and current StateMemory.',
        'Actions must expose failures explicitly and must not silently ignore errors.',
    ],
    examples: ['fsm-action'],
    diagrams: ['fsm-runtime'],
    introducedIn: 'P112Q3E1'
)]
interface StateActionInterface
{
    /**
     * PUBLIC API
     *
     * Role:
     *   Execute a declared transition action.
     *
     * @param TransitionDefinition $transition Validated transition definition.
     * @param StateMemory $memory Mutable FSM memory object for this state machine instance.
     *
     * @return void
     *
     * @throws StateMachineException When the action contract fails.
     *
     * Side effects:
     *   May mutate the provided StateMemory only.
     *
     * Contract:
     *   Must not route, render, authorize, or silently ignore failure.
     */
    #[OpusRefBookMethod(
        role: 'Execute one declared FSM transition action',
        behavior: 'Runs an explicit side effect after StateMachine has selected and validated a transition.',
        preconditions: [
            'The transition argument has already been selected by StateMachine.',
            'The memory argument belongs to the active StateMachine instance.',
        ],
        postconditions: ['Any mutation is limited to the provided StateMemory or explicit failure is raised.'],
        sideEffects: ['May mutate the provided StateMemory only.'],
        errors: ['FSM_CONTRACT_FAILED', 'FSM_MEMORY_CONTRACT_FAILED'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-action'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function execute(TransitionDefinition $transition, StateMemory $memory): void;
}
