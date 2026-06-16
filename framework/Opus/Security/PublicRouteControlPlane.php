<?php

declare(strict_types=1);

namespace Opus\Security;

use Opus\Http\PublicRequest;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Demonstrate the OPUS public route FSM/ACL/SSO-like control plane baseline.
 *
 * Responsibility:
 *   Convert a simple public route profile into explicit internal control
 *   metadata and deny anything outside the declared public gate.
 *
 * Contract:
 *   This class makes control decisions only. It does not execute controllers,
 *   render views, expose public diagnostics or implement business tools.
 */
final class PublicRouteControlPlane
{
    public function authorize(PublicRequest $request, string $routeProfile): PublicControlDecision
    {
        if ($routeProfile !== 'public_page') {
            return $this->deny($request, 'PROFILE_NOT_PUBLIC_PAGE');
        }

        if ($request->method() !== 'GET') {
            return $this->deny($request, 'METHOD_NOT_PUBLIC_GET');
        }

        return PublicControlDecision::allowed(
            $this->eventId($request, 'PUBLIC_ROUTE_ALLOWED'),
            [
                'site' => $request->site(),
                'route_key' => $request->routeKey(),
                'identity_context' => 'anonymous_public',
                'fsm_state' => 'PUBLIC_BROWSING',
                'fsm_transition' => 'VIEW_PUBLIC_PAGE',
                'acl_policy' => 'PUBLIC_READ',
                'decision' => 'ALLOW',
            ]
        );
    }

    public function denyUnknownRoute(PublicRequest $request): PublicControlDecision
    {
        return $this->deny($request, 'UNKNOWN_PUBLIC_ROUTE');
    }

    private function deny(PublicRequest $request, string $reason): PublicControlDecision
    {
        $eventId = $this->eventId($request, $reason);
        $blockedEvent = BlockedStateEvent::publicRequestBlocked(
            $eventId,
            $request->site(),
            $request->routeKey(),
            'PUBLIC_REQUEST_BLOCKED',
            $reason,
            'ADMIN_VIEW_BLOCKED_STATES'
        );

        return PublicControlDecision::denied(
            $eventId,
            [
                'site' => $request->site(),
                'route_key' => $request->routeKey(),
                'identity_context' => 'anonymous_public',
                'fsm_state' => 'PUBLIC_BROWSING',
                'blocked_state' => 'PUBLIC_REQUEST_BLOCKED',
                'decision' => 'DENY',
                'reason' => $reason,
                'admin_action' => 'ADMIN_VIEW_BLOCKED_STATES',
                'blocked_event' => $blockedEvent->adminDiagnostics(),
            ],
            $blockedEvent
        );
    }

    private function eventId(PublicRequest $request, string $reason): string
    {
        $hash = strtoupper(substr(hash('sha256', $request->site() . '|' . $request->routeKey() . '|' . $reason), 0, 12));

        return 'OPUS-EVT-' . gmdate('Ymd') . '-' . $hash;
    }
}
