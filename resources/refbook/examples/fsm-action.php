<?php
declare(strict_types=1);

/*
 * Opus RefBook example: FSM action.
 *
 * Purpose:
 *   Keep side effects behind an explicit transition action.
 */

use ASAP\Fsm\StateActionInterface;
use ASAP\Fsm\TransitionResult;

final class PublishValidatedPageAction implements StateActionInterface
{
    public function execute(TransitionResult $result): void
    {
        if ($result->toState() !== 'VALIDATED') {
            throw new RuntimeException('OPUS_FSM_ACTION_STATE_INVALID');
        }

        // Execute the official side effect for this transition only.
    }
}
