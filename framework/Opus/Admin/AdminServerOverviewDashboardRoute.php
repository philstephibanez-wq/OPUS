<?php

declare(strict_types=1);

namespace Opus\Admin;

use Opus\Http\PublicResponse;
use Opus\Server\ServerOverviewSnapshot;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Serve the native OPUS server control-plane overview route.
 *
 * Responsibility:
 *   Render multi-site supervision only after the route access control plane has
 *   allowed the request.
 *
 * Contract:
 *   This route is read-only. It does not mutate site state, ACL, authentication,
 *   FSM state or Apache configuration.
 */
final class AdminServerOverviewDashboardRoute
{
    public function __construct(
        private readonly AdminServerOverviewAccessControlPlane $controlPlane = new AdminServerOverviewAccessControlPlane(),
        private readonly AdminServerOverviewDashboardResponseRenderer $renderer = new AdminServerOverviewDashboardResponseRenderer()
    ) {
    }

    public function handle(AdminRouteRequest $request, ServerOverviewSnapshot $snapshot): AdminDashboardResponse|PublicResponse
    {
        $decision = $this->controlPlane->authorize($request);
        if (!$decision->isAllowed()) {
            return $decision->publicResponse();
        }

        return $this->renderer->render($decision, $snapshot);
    }
}