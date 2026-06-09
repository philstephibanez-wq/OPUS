<?php
declare(strict_types=1);

/*
 * ASAP RefBook example: FSM basic transition.
 *
 * Purpose:
 *   Apply one explicit signal and read the resulting state.
 */

use ASAP\Fsm\Fsm;
use ASAP\Fsm\StateMemory;

$memory = new StateMemory('DRAFT');

$result = $fsm->apply($memory, 'VALIDATE');

if ($result->fromState() !== 'DRAFT' || $result->toState() !== 'VALIDATED') {
    throw new RuntimeException('ASAP_FSM_TRANSITION_UNEXPECTED');
}

$memory->set($result->toState());
