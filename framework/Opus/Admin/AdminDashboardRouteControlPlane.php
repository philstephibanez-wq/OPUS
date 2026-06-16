<?php

declare(strict_types=1);

namespace Opus\Admin;

use Opus\Http\PublicResponse;
use Opus\Security\BlockedStateEvent;
use Opus\Security\PublicBlockedResponseRenderer;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Protect the native OPUS administrator dashboard route as part of the
 *   framework control plane.
 *
 * Responsibility:
 *   Validate the admin dashboard route intent, FSM transition, role and scope
 *   before any admin dashboard payload is returned.
 *
 * Contract:
 *   This control plane never renders administrator diagnostics publicly. Any
 *   denied access returns only the OPUS opaque support response while retaining
 *   protected diagnostics for admin/log/report surfaces.
 */
final class AdminDashboardRouteControlPlane
{
    public const DASHBOARD_PROFILE = 'admin_dashboard';
    public const BLOCKED_STATES_PATH = '/admin/blocked-states';
    public const REQUIRED_SCOPE = 'ADMIN_VIEW_BLOCKED_STATES';

    public function authorizeBlockedStates(AdminRouteRequest $request, BlockedStateEvent $event): AdminRouteControlDecision
    {
        $reason = $this->denialReason($request);
        if ($reason !== null) {
            return $this->deny($request, $reason);
        }

        $eventId = $this->eventId($request, 'ADMIN_ROUTE_ALLOWED');
        $viewModel = AdminBlockedStateViewModel::fromBlockedStateEvent($event);

        return AdminRouteControlDecision::allowed(
            $eventId,
            [
                'surface' => 'admin_dashboard',
                'route_key' => $request->routeKey(),
                'identity_context' => $request->identityContext(),
                'fsm_state' => 'ADMIN_DASHBOARD_READY',
                'fsm_transition' => self::REQUIRED_SCOPE,
                'acl_policy' => 'ADMIN_BLOCKED_STATES_READ',
                'decision' => 'ALLOW',
                'dashboard_profile' => self::DASHBOARD_PROFILE,
            ],
            $viewModel
        );
    }

    private function denialReason(AdminRouteRequest $request): ?string
    {
        if ($request->method() !== 'GET') {
            return 'ADMIN_DASHBOARD_METHOD_NOT_GET';
        }

        if ($request->path() !== self::BLOCKED_STATES_PATH) {
            return 'ADMIN_DASHBOARD_ROUTE_UNKNOWN';
        }

        if (!$request->hasRole('admin')) {
            return 'ADMIN_DASHBOARD_ROLE_DENIED';
        }

        if (!$request->hasScope(self::REQUIRED_SCOPE)) {
            return 'ADMIN_DASHBOARD_SCOPE_DENIED';
        }

        return null;
    }

    private function deny(AdminRouteRequest $request, string $reason): AdminRouteControlDecision
    {
        $eventId = $this->eventId($request, $reason);
        $blockedEvent = BlockedStateEvent::publicRequestBlocked(
            $eventId,
            $request->site(),
            $request->routeKey(),
            'ADMIN_DASHBOARD_ACCESS_BLOCKED',
            $reason,
            'ADMIN_REVIEW_DASHBOARD_ACCESS'
        );
        $publicResponse = (new PublicBlockedResponseRenderer())->render($blockedEvent);

        return AdminRouteControlDecision::denied(
            $eventId,
            [
                'surface' => 'admin_dashboard',
                'route_key' => $request->routeKey(),
                'identity_context' => $request->identityContext(),
                'fsm_state' => 'ADMIN_DASHBOARD_REQUESTED',
                'blocked_state' => 'ADMIN_DASHBOARD_ACCESS_BLOCKED',
                'decision' => 'DENY',
                'reason' => $reason,
                'admin_action' => 'ADMIN_REVIEW_DASHBOARD_ACCESS',
                'dashboard_profile' => self::DASHBOARD_PROFILE,
            ],
            $publicResponse,
            $blockedEvent
        );
    }

    private function eventId(AdminRouteRequest $request, string $reason): string
    {
        $hash = strtoupper(substr(hash('sha256', $request->site() . '|' . $request->routeKey() . '|' . $request->identityContext() . '|' . $reason), 0, 12));

        return 'OPUS-ADM-' . gmdate('Ymd') . '-' . $hash;
    }
}
