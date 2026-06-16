<?php

declare(strict_types=1);

namespace Opus\Admin;

use Opus\Security\BlockedStateEvent;
use Opus\Security\PublicBlockedResponseRenderer;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Authorize the OPUS server overview dashboard route.
 *
 * Responsibility:
 *   Enforce the current admin bootstrap contract through explicit route, method,
 *   role and scope checks before the multi-site server dashboard is rendered.
 *
 * Contract:
 *   This is the first read-only server control-plane gate. It must not expose
 *   denial reasons publicly. Future SSO/Identity providers plug in before this
 *   gate by creating a correctly scoped AdminRouteRequest.
 */
final class AdminServerOverviewAccessControlPlane
{
    public const SERVER_OVERVIEW_PATH = '/admin/server-overview';
    public const REQUIRED_SCOPE = 'ADMIN_VIEW_SERVER_OVERVIEW';
    private const DASHBOARD_PROFILE = 'server_control_plane';

    public function authorize(AdminRouteRequest $request): AdminServerOverviewAccessDecision
    {
        $reason = $this->denialReason($request);
        if ($reason !== null) {
            return $this->deny($request, $reason);
        }

        $eventId = $this->eventId($request, 'ADMIN_SERVER_OVERVIEW_ALLOWED');

        return AdminServerOverviewAccessDecision::allowed(
            $eventId,
            [
                'surface' => 'admin_dashboard',
                'route_key' => $request->routeKey(),
                'identity_context' => $request->identityContext(),
                'fsm_state' => 'SERVER_CONTROL_PLANE_READY',
                'fsm_transition' => self::REQUIRED_SCOPE,
                'acl_policy' => 'ADMIN_SERVER_OVERVIEW_READ',
                'decision' => 'ALLOW',
                'dashboard_profile' => self::DASHBOARD_PROFILE,
            ]
        );
    }

    private function denialReason(AdminRouteRequest $request): ?string
    {
        if ($request->method() !== 'GET') {
            return 'ADMIN_SERVER_OVERVIEW_METHOD_NOT_GET';
        }

        if ($request->path() !== self::SERVER_OVERVIEW_PATH) {
            return 'ADMIN_SERVER_OVERVIEW_ROUTE_UNKNOWN';
        }

        if (!$request->hasRole('admin')) {
            return 'ADMIN_SERVER_OVERVIEW_ROLE_DENIED';
        }

        if (!$request->hasScope(self::REQUIRED_SCOPE)) {
            return 'ADMIN_SERVER_OVERVIEW_SCOPE_DENIED';
        }

        return null;
    }

    private function deny(AdminRouteRequest $request, string $reason): AdminServerOverviewAccessDecision
    {
        $eventId = $this->eventId($request, $reason);
        $blockedEvent = BlockedStateEvent::publicRequestBlocked(
            $eventId,
            $request->site(),
            $request->routeKey(),
            'ADMIN_SERVER_OVERVIEW_ACCESS_BLOCKED',
            $reason,
            'ADMIN_REVIEW_SERVER_CONTROL_PLANE_ACCESS'
        );

        return AdminServerOverviewAccessDecision::denied(
            $eventId,
            [
                'surface' => 'admin_dashboard',
                'route_key' => $request->routeKey(),
                'identity_context' => $request->identityContext(),
                'fsm_state' => 'SERVER_CONTROL_PLANE_REQUESTED',
                'blocked_state' => 'ADMIN_SERVER_OVERVIEW_ACCESS_BLOCKED',
                'decision' => 'DENY',
                'reason' => $reason,
                'admin_action' => 'ADMIN_REVIEW_SERVER_CONTROL_PLANE_ACCESS',
                'dashboard_profile' => self::DASHBOARD_PROFILE,
            ],
            (new PublicBlockedResponseRenderer())->render($blockedEvent),
            $blockedEvent
        );
    }

    private function eventId(AdminRouteRequest $request, string $reason): string
    {
        $hash = strtoupper(substr(hash('sha256', $request->site() . '|' . $request->routeKey() . '|' . $request->identityContext() . '|' . $reason), 0, 12));

        return 'OPUS-SRV-' . gmdate('Ymd') . '-' . $hash;
    }
}