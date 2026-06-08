<?php

declare(strict_types=1);

namespace ASAP\Security;

use ASAP\Acl\AccessContext;
use ASAP\Acl\AccessControl;
use ASAP\Acl\AccessControlException;
use ASAP\Contract\ContractException;
use ASAP\Fsm\StateMachine;
use ASAP\Http\Request;
use ASAP\Routing\RouteMatch;

/*
 * ASAP_REFBOOK:
 *   domain: SECURITY
 *   role: Class SecureDispatchGate belongs to the SECURITY ASAP framework domain.
 *   contract:
 *     - keeps responsibility limited to the SECURITY domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - secure-dispatch-gate
 *   diagrams:
 *     - secure-dispatch-runtime
 * END_ASAP_REFBOOK
 */
/**
 * PUBLIC SECURITY GATE
 *
 * Role:
 *   Validate the route-aware secure-by-design boundary before controller dispatch.
 *
 * Responsibility:
 *   Execute the declared FSM signal, evaluate the explicit ACL decision and return
 *   an observable decision object for diagnostics and recipes.
 *
 * Contract:
 *   No controller/action dispatch may happen before this gate succeeds. Unknown FSM
 *   transitions, malformed route ACL metadata, unknown ACL declarations and denied
 *   ACL rules must fail explicitly. The site policy remains the official baseline
 *   policy when a route does not declare stricter route metadata.
 *
 * Since:
 *   P112Q3B
 */
final class SecureDispatchGate
{
    /**
     * PUBLIC API
     *
     * Role:
     *   Assert that a matched route is allowed by the official FSM and ACL contracts.
     *
     * @param Request $request Normalized HTTP request.
     * @param SiteSecurityPolicy $policy Typed site security policy.
     * @param RouteMatch $match Matched route candidate.
     *
     * @return SecureDispatchDecision Observable successful decision.
     *
     * @throws ContractException When route security metadata is malformed.
     * @throws AccessControlException When ACL denies access or declarations are invalid.
     */
    public function assertAllowed(Request $request, SiteSecurityPolicy $policy, RouteMatch $match): SecureDispatchDecision
    {
        $fsmSignal = $this->resolveFsmSignal($policy, $match);
        $machine = new StateMachine($policy->states, $policy->transitions, $policy->initialState);
        $fsmResult = $machine->apply($fsmSignal);

        [$role, $resource, $privilege, $source] = $this->resolveAclTriplet($policy, $match);

        $acl = new AccessControl($policy->roles, $policy->resources, $policy->privileges, $policy->rules);
        $decision = $acl->decide(
            $role,
            $resource,
            $privilege,
            new AccessContext([
                'path' => $request->path,
                'method' => $request->method,
                'route' => $match->name,
                'route_acl' => $match->acl,
                'route_fsm_guard' => $match->fsmGuard,
                'fsm_signal' => $fsmSignal,
                'fsm_state' => $fsmResult->toState(),
                'security_metadata_source' => $source,
            ])
        );

        if (!$decision->allowed()) {
            throw AccessControlException::contract(AccessControlException::ACCESS_DENIED, $decision->reason());
        }

        return new SecureDispatchDecision(
            $match->name,
            $fsmResult->toState(),
            $fsmSignal,
            $role,
            $resource,
            $privilege,
            $source
        );
    }

    /**
     * INTERNAL RESOLVER
     *
     * @return string Explicit FSM signal for this request.
     */
    private function resolveFsmSignal(SiteSecurityPolicy $policy, RouteMatch $match): string
    {
        $routeSignal = trim((string) ($match->fsmGuard ?? ''));

        if ($routeSignal !== '') {
            return $routeSignal;
        }

        return $policy->requestSignal;
    }

    /**
     * INTERNAL RESOLVER
     *
     * Role:
     *   Resolve route-aware ACL metadata while preserving the declared site policy
     *   baseline when no route-specific metadata exists.
     *
     * Supported route ACL formats:
     *   - `resource:privilege`
     *   - `role:resource:privilege`
     *
     * @return array{0:string,1:string,2:string,3:string} role/resource/privilege/source.
     */
    private function resolveAclTriplet(SiteSecurityPolicy $policy, RouteMatch $match): array
    {
        $routeAcl = trim((string) ($match->acl ?? ''));

        if ($routeAcl === '') {
            return [$policy->role, $policy->resource, $policy->privilege, 'policy'];
        }

        $parts = array_values(array_filter(array_map('trim', explode(':', $routeAcl)), static fn (string $value): bool => $value !== ''));

        if (count($parts) === 2) {
            return [$policy->role, $parts[0], $parts[1], 'route'];
        }

        if (count($parts) === 3) {
            return [$parts[0], $parts[1], $parts[2], 'route'];
        }

        throw ContractException::because('ASAP_ROUTE_ACL_METADATA_INVALID', $match->name . ' => ' . $routeAcl);
    }
}
