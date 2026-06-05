<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the small top-level `ASAP\Fsm` demo surface.
 *
 * Contract:
 *   Demo data only. Runtime FSM execution belongs to `ASAP\FSM\StateMachine`.
 *
 * Since:
 *   P112P1
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
