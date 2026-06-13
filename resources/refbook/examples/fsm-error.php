<?php
declare(strict_types=1);

/*
 * Opus RefBook example: FSM error.
 *
 * Purpose:
 *   Demonstrate the expected explicit failure on an invalid signal.
 */

use ASAP\Fsm\StateMachineException;

try {
    $fsm->apply($memory, 'UNKNOWN_SIGNAL');
} catch (StateMachineException $exception) {
    // Expected contract failure.
    // The caller must handle or display the explicit FSM error.
    error_log($exception->getMessage());
}
