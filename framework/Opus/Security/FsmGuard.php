<?php

declare(strict_types=1);

namespace Opus\Security;

use ASAP\Fsm\StateMachine;
use ASAP\Http\Request;

/*
 * OPUS_REFBOOK:
 *   domain: SECURITY
 *   role: Class FsmGuard belongs to the SECURITY Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the SECURITY domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - security-overview
 *   diagrams:
 *     - security-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC GUARD
 *
 * Role:
 *   Validate the request state transition through the official Opus FSM.
 *
 * Responsibility:
 *   Execute exactly one declared request signal against one site security policy.
 *
 * Contract:
 *   FSM Guard owns state validation only. It does not route, authorize,
 *   dispatch controllers or render HTML.
 *
 * Since:
 *   P112D2
 */
final class FsmGuard
{
    /**
     * PUBLIC API
     *
     * @param Request $request Normalized request.
     * @param SiteSecurityPolicy $policy Site security policy.
     *
     * @return string State reached after the guard transition.
     */
    public function assertAllowed(Request $request, SiteSecurityPolicy $policy): string
    {
        $machine = new StateMachine($policy->states, $policy->transitions, $policy->initialState);
        $result = $machine->apply($policy->requestSignal);

        return $result->toState();
    }
}
