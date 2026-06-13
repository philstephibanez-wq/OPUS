<?php

declare(strict_types=1);

namespace Opus\Security;

use ASAP\Acl\AccessContext;
use ASAP\Acl\AccessControl;
use ASAP\Acl\AccessControlException;
use ASAP\Http\Request;

/*
 * OPUS_REFBOOK:
 *   domain: SECURITY
 *   role: Class AclGuard belongs to the SECURITY Opus framework domain.
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
 *   Validate request access through the official Opus ACL.
 *
 * Responsibility:
 *   Evaluate one explicit role/resource/privilege decision for the resolved site.
 *
 * Contract:
 *   ACL Guard owns authorization only. It does not route, transition FSM state,
 *   dispatch controllers or render HTML.
 *
 * Since:
 *   P112D2
 */
final class AclGuard
{
    /**
     * PUBLIC API
     *
     * @param Request $request Normalized request.
     * @param SiteSecurityPolicy $policy Site security policy.
     * @param string $fsmState State validated by the FSM guard.
     *
     * @return void
     */
    public function assertAllowed(Request $request, SiteSecurityPolicy $policy, string $fsmState): void
    {
        $acl = new AccessControl($policy->roles, $policy->resources, $policy->privileges, $policy->rules);

        $decision = $acl->decide(
            $policy->role,
            $policy->resource,
            $policy->privilege,
            new AccessContext([
                'path' => $request->path,
                'method' => $request->method,
                'fsm_state' => $fsmState,
            ])
        );

        if (!$decision->allowed()) {
            throw AccessControlException::contract(AccessControlException::ACCESS_DENIED, $decision->reason());
        }
    }
}
