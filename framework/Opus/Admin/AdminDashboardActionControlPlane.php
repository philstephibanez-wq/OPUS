<?php

declare(strict_types=1);

namespace Opus\Admin;

use Opus\Security\BlockedStateEvent;
use Opus\Security\PublicBlockedResponseRenderer;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Protect native OPUS administrator dashboard actions as part of the framework
 *   control plane.
 *
 * Responsibility:
 *   Validate declared action intent, role and scope before an admin dashboard
 *   action effect is returned.
 *
 * Contract:
 *   No dashboard action may bypass FSM/ACL/identity checks. Denied actions return
 *   only the opaque public support response while protected diagnostics stay in
 *   admin/log/report surfaces.
 */
final class AdminDashboardActionControlPlane
{
    public const ACKNOWLEDGE_BLOCKED_STATE_ACTION = 'ADMIN_ACKNOWLEDGE_BLOCKED_STATE';
    public const ACKNOWLEDGE_BLOCKED_STATE_SCOPE = 'ADMIN_ACKNOWLEDGE_BLOCKED_STATE';

    public function authorizeAcknowledgeBlockedState(AdminDashboardActionRequest $request, BlockedStateEvent $event): AdminDashboardActionDecision
    {
        $reason = $this->denialReason($request);
        if ($reason !== null) {
            return $this->deny($request, $reason);
        }

        $eventId = $this->eventId($request, 'ADMIN_DASHBOARD_ACTION_ALLOWED');

        return AdminDashboardActionDecision::granted(
            $eventId,
            self::ACKNOWLEDGE_BLOCKED_STATE_ACTION,
            [
                'surface' => 'admin_dashboard',
                'route_key' => $request->routeKey(),
                'identity_context' => $request->identityContext(),
                'fsm_state' => 'ADMIN_DASHBOARD_ACTION_READY',
                'fsm_transition' => self::ACKNOWLEDGE_BLOCKED_STATE_ACTION,
                'acl_policy' => 'ADMIN_BLOCKED_STATES_ACKNOWLEDGE',
                'decision' => 'ALLOW',
                'admin_action' => self::ACKNOWLEDGE_BLOCKED_STATE_ACTION,
                'source_blocked_state_event_id' => $event->eventId(),
            ],
            'blocked_state_acknowledged'
        );
    }

    private function denialReason(AdminDashboardActionRequest $request): ?string
    {
        if ($request->action() !== self::ACKNOWLEDGE_BLOCKED_STATE_ACTION) {
            return 'ADMIN_DASHBOARD_ACTION_UNKNOWN';
        }

        if (!$request->hasRole('admin')) {
            return 'ADMIN_DASHBOARD_ACTION_ROLE_DENIED';
        }

        if (!$request->hasScope(self::ACKNOWLEDGE_BLOCKED_STATE_SCOPE)) {
            return 'ADMIN_DASHBOARD_ACTION_SCOPE_DENIED';
        }

        return null;
    }

    private function deny(AdminDashboardActionRequest $request, string $reason): AdminDashboardActionDecision
    {
        $eventId = $this->eventId($request, $reason);
        $blockedEvent = BlockedStateEvent::publicRequestBlocked(
            $eventId,
            $request->site(),
            $request->routeKey(),
            'ADMIN_DASHBOARD_ACTION_BLOCKED',
            $reason,
            'ADMIN_REVIEW_DASHBOARD_ACTION'
        );
        $publicResponse = (new PublicBlockedResponseRenderer())->render($blockedEvent);

        return AdminDashboardActionDecision::denied(
            $eventId,
            $request->action(),
            [
                'surface' => 'admin_dashboard',
                'route_key' => $request->routeKey(),
                'identity_context' => $request->identityContext(),
                'fsm_state' => 'ADMIN_DASHBOARD_ACTION_REQUESTED',
                'blocked_state' => 'ADMIN_DASHBOARD_ACTION_BLOCKED',
                'decision' => 'DENY',
                'reason' => $reason,
                'admin_action' => 'ADMIN_REVIEW_DASHBOARD_ACTION',
                'requested_action' => $request->action(),
            ],
            $publicResponse,
            $blockedEvent
        );
    }

    private function eventId(AdminDashboardActionRequest $request, string $reason): string
    {
        $hash = strtoupper(substr(hash('sha256', $request->site() . '|' . $request->routeKey() . '|' . $request->identityContext() . '|' . $request->action() . '|' . $reason), 0, 12));

        return 'OPUS-ADM-ACT-' . gmdate('Ymd') . '-' . $hash;
    }
}
