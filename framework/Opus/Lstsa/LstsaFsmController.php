<?php

declare(strict_types=1);

namespace Opus\Lstsa;

use ASAP\Fsm\StateDefinition;
use ASAP\Fsm\StateMachine;
use ASAP\Fsm\TransitionDefinition;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaFsmController belongs to the LSTSA Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LSTSA domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - lstsa-overview
 *   diagrams:
 *     - lstsa-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LSTSAR FSM CONTROLLER
 *
 * @visibility public
 * @role Applies the official Load/Secure/Transform/Store/Archive/Report state
 *       transitions used by the non-blocking Lstsa background runner.
 * @contract The runner may execute a phase only after this controller accepts
 *           the transition. Unknown states or signals fail explicitly through
 *           the underlying Opus FSM engine.
 * @sideEffects None. This object computes transitions; LstsaRunStore persists
 *              the resulting run state and checkpoints.
 */
final class LstsaFsmController
{
    /**
     * PUBLIC API
     *
     * @param string $currentState Current persisted run state.
     * @param string $signal Explicit transition signal.
     * @return string Next state authorized by the FSM.
     */
    public function apply(string $currentState, string $signal): string
    {
        $machine = new StateMachine($this->states(), $this->transitions(), $currentState);
        return $machine->apply($signal)->toState();
    }

    /**
     * @return list<StateDefinition>
     */
    private function states(): array
    {
        return array_map(
            static fn(string $state): StateDefinition => new StateDefinition($state),
            LstsaFsmState::all()
        );
    }

    /**
     * @return list<TransitionDefinition>
     */
    private function transitions(): array
    {
        $transitions = [
            new TransitionDefinition(LstsaFsmState::ACQUIRED, LstsaFsmSignal::START, LstsaFsmState::LOAD_REQUIRED),
            new TransitionDefinition(LstsaFsmState::LOAD_REQUIRED, LstsaFsmSignal::LOAD_OK, LstsaFsmState::SECURE_INPUT_REQUIRED),
            new TransitionDefinition(LstsaFsmState::SECURE_INPUT_REQUIRED, LstsaFsmSignal::SECURE_INPUT_OK, LstsaFsmState::TRANSFORM_REQUIRED),
            new TransitionDefinition(LstsaFsmState::TRANSFORM_REQUIRED, LstsaFsmSignal::TRANSFORM_OK, LstsaFsmState::SECURE_OUTPUT_REQUIRED),
            new TransitionDefinition(LstsaFsmState::SECURE_OUTPUT_REQUIRED, LstsaFsmSignal::SECURE_OUTPUT_OK, LstsaFsmState::STORE_REQUIRED),
            new TransitionDefinition(LstsaFsmState::STORE_REQUIRED, LstsaFsmSignal::STORE_OK, LstsaFsmState::ARCHIVE_REQUIRED),
            new TransitionDefinition(LstsaFsmState::ARCHIVE_REQUIRED, LstsaFsmSignal::ARCHIVE_OK, LstsaFsmState::REPORT_REQUIRED),
            new TransitionDefinition(LstsaFsmState::REPORT_REQUIRED, LstsaFsmSignal::REPORT_OK, LstsaFsmState::DONE),
        ];

        foreach (LstsaFsmState::all() as $state) {
            if (!in_array($state, [LstsaFsmState::DONE, LstsaFsmState::FAILED], true)) {
                $transitions[] = new TransitionDefinition($state, LstsaFsmSignal::FAIL, LstsaFsmState::FAILED);
            }
        }

        return $transitions;
    }
}
