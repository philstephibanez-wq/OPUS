<?php

declare(strict_types=1);

namespace ASAP\Security;

/*
 * ASAP_REFBOOK:
 *   domain: SECURITY
 *   role: Class SecureDispatchDecision belongs to the SECURITY ASAP framework domain.
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
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry the route-aware result accepted by SecureDispatchGate.
 *
 * Responsibility:
 *   Expose the validated route name, FSM state, ACL role/resource/privilege and
 *   metadata source for diagnostics, recipes and future RefBook diagrams.
 *
 * Contract:
 *   Data only. It never authorizes, routes, dispatches or renders.
 *
 * Since:
 *   P112Q3B
 */
final class SecureDispatchDecision
{
    /**
     * PUBLIC DTO CONSTRUCTOR
     *
     * @param string $routeName Matched route name.
     * @param string $fsmState State reached after FSM validation.
     * @param string $fsmSignal FSM signal applied for this request.
     * @param string $role ACL role evaluated.
     * @param string $resource ACL resource evaluated.
     * @param string $privilege ACL privilege evaluated.
     * @param string $metadataSource `route` when route metadata overrides policy, otherwise `policy`.
     */
    public function __construct(
        public readonly string $routeName,
        public readonly string $fsmState,
        public readonly string $fsmSignal,
        public readonly string $role,
        public readonly string $resource,
        public readonly string $privilege,
        public readonly string $metadataSource
    ) {
    }
}
