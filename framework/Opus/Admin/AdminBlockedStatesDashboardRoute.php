<?php

declare(strict_types=1);

namespace Opus\Admin;

use Opus\Security\BlockedStateEvent;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Represent the first native OPUS administrator dashboard route surface.
 *
 * Responsibility:
 *   Serve the blocked-state dashboard route only after the OPUS admin dashboard
 *   control plane has authorized the request.
 *
 * Contract:
 *   This route is part of OPUS. It must not bypass FSM, ACL or identity checks.
 *   Denied access returns only an opaque public response via the control plane.
 */
final class AdminBlockedStatesDashboardRoute
{
    public function __construct(private readonly AdminDashboardRouteControlPlane $controlPlane = new AdminDashboardRouteControlPlane())
    {
    }

    public function handle(AdminRouteRequest $request, BlockedStateEvent $event): AdminRouteControlDecision
    {
        return $this->controlPlane->authorizeBlockedStates($request, $event);
    }
}
