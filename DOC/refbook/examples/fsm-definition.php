<?php
declare(strict_types=1);

/*
 * ASAP RefBook example: FSM definition.
 *
 * Purpose:
 *   Define a tiny state machine with explicit states and signals.
 */

use ASAP\Fsm\Fsm;
use ASAP\Fsm\StateDefinition;
use ASAP\Fsm\SignalDefinition;
use ASAP\Fsm\TransitionDefinition;

$fsm = new Fsm(
    states: [
        new StateDefinition('DRAFT', 'Draft'),
        new StateDefinition('VALIDATED', 'Validated'),
    ],
    signals: [
        new SignalDefinition('VALIDATE'),
    ],
    transitions: [
        TransitionDefinition::fromTo(
            fromState: 'DRAFT',
            signal: 'VALIDATE',
            toState: 'VALIDATED'
        ),
    ],
    initialState: 'DRAFT',
);
