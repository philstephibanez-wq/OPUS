<?php
declare(strict_types=1);

/*
 * ASAP RefBook example: secure dispatch gate.
 *
 * Purpose:
 *   Route matching, ACL and FSM metadata are validated before controller action.
 */

use ASAP\Routing\RouteMatch;

function dispatchWithGate(RouteMatch $match): void
{
    if ($match->acl !== null) {
        // ACL validation must happen explicitly before dispatch.
    }

    if ($match->fsmGuard !== null) {
        // FSM guard validation must happen explicitly before dispatch.
    }

    // Only a validated match reaches the controller dispatcher.
}
