<?php

declare(strict_types=1);

namespace ASAP\Fsm;

/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the small top-level `ASAP\Fsm` demo surface.
 *
 * Contract:
 *   Demo data only. Runtime FSM execution belongs to `ASAP\Fsm\StateMachine`.
 *
 * Since:
 *   P112P1
 /**
 * ASAP_REFBOOK:
 *   domain: FSM
 *   role: Public facade for ASAP finite-state workflow services.
 *   contract:
 *     - keeps FSM access behind an explicit framework API
 *     - does not render UI or execute unrelated business logic
 *     - must fail explicitly when FSM contracts are invalid
 *   examples:
 *     - fsm-basic-transition
 *   diagrams:
 *     - fsm-runtime
 * END_ASAP_REFBOOK
 */
 */
final class Fsm
{
    /** @return array{states:string[],signals:string[],initial:string} */
    public static function demoFlow(): array
    {
        return [
            'states' => ['START', 'DONE'],
            'signals' => ['NEXT'],
            'initial' => 'START',
        ];
    }
}
