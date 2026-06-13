<?php
declare(strict_types=1);

/*
 * Opus RefBook example: FSM basic transition.
 *
 * Purpose:
 *   Apply one explicit signal and read the resulting state.
 */

use ASAP\Fsm\Fsm;
use ASAP\Fsm\StateMemory;

$memory = new StateMemory('DRAFT');

$result = $fsm->apply($memory, 'VALIDATE');

if ($result->fromState() !== 'DRAFT' || $result->toState() !== 'VALIDATED') {
    throw new RuntimeException('OPUS_FSM_TRANSITION_UNEXPECTED');
}

$memory->set($result->toState());
