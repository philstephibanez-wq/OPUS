<?php

declare(strict_types=1);

namespace ASAP\Routing;

/*
 * ASAP_REFBOOK:
 *   domain: ROUTING
 *   role: Class RouteMatch belongs to the ROUTING ASAP framework domain.
 *   contract:
 *     - keeps responsibility limited to the ROUTING domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - routing-overview
 *     - secure-dispatch-gate
 *   diagrams:
 *     - routing-runtime
 *     - secure-dispatch-runtime
 * END_ASAP_REFBOOK
 */
/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry the result of a successful route match.
 *
 * Responsibility:
 *   Provide controller class, action, route parameters and route-level security
 *   metadata to the secure dispatch gate and controller dispatcher.
 *
 * Contract:
 *   Data only. No dispatch, no rendering, no authorization decision.
 *
 * Since:
 *   P112D1
 *
 * Extended:
 *   P112Q3B carries route-aware ACL and FSM metadata.
 */
final class RouteMatch
{
    /**
     * PUBLIC DTO CONSTRUCTOR
     *
     * @param string $name Matched route name.
     * @param string $controllerClass Explicit controller class.
     * @param string $action Explicit controller action method.
     * @param array<string,string> $params Matched route parameters.
     * @param string|null $acl Optional route ACL override, using `resource:privilege` or `role:resource:privilege`.
     * @param string|null $fsmGuard Optional route FSM signal override.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $controllerClass,
        public readonly string $action,
        public readonly array $params,
        public readonly ?string $acl = null,
        public readonly ?string $fsmGuard = null
    ) {
    }
}
