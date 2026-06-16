<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Admin\AdminDashboardActionAuditProjection;
use Opus\Admin\AdminDashboardActionAuditTrail;
use Opus\Admin\AdminDashboardActionControlPlane;
use Opus\Admin\AdminDashboardActionRequest;
use Opus\Http\PublicRequest;
use Opus\Security\PublicRouteControlPlane;
use RuntimeException;

final class NativeAdminDashboardActionAuditProjectionSmoke
{
    /** @return array<string,mixed> */
    public static function run(): array
    {
        $publicRequest = PublicRequest::get('/missing', 'opus-demo');
        $event = (new PublicRouteControlPlane())->denyUnknownRoute($publicRequest)->blockedStateEvent();
        if ($event === null) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_SOURCE_EVENT_MISSING');
        }

        $controlPlane = new AdminDashboardActionControlPlane();
        $allowed = $controlPlane->authorizeAcknowledgeBlockedState(
            AdminDashboardActionRequest::admin(
                AdminDashboardActionControlPlane::ACKNOWLEDGE_BLOCKED_STATE_ACTION,
                'opus-demo',
                'local_admin_projection_smoke',
                [AdminDashboardActionControlPlane::ACKNOWLEDGE_BLOCKED_STATE_SCOPE]
            ),
            $event
        );

        if (!$allowed->isGranted()) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_ALLOWED_REQUEST_DENIED');
        }

        $denied = $controlPlane->authorizeAcknowledgeBlockedState(
            AdminDashboardActionRequest::admin(
                AdminDashboardActionControlPlane::ACKNOWLEDGE_BLOCKED_STATE_ACTION,
                'opus-demo',
                'local_admin_projection_missing_scope_smoke',
                []
            ),
            $event
        );

        if ($denied->isGranted()) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_DENIED_REQUEST_GRANTED');
        }

        $trail = new AdminDashboardActionAuditTrail();
        $trail->record($allowed);
        $trail->record($denied);

        $projection = AdminDashboardActionAuditProjection::fromAuditTrail($trail);
        $decisions = $projection->decisions();
        $actions = $projection->actions();

        if ($projection->count() !== 2) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_COUNT_INVALID');
        }

        if ($decisions !== ['ALLOW', 'DENY']) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_DECISIONS_INVALID');
        }

        if ($actions !== [AdminDashboardActionControlPlane::ACKNOWLEDGE_BLOCKED_STATE_ACTION, AdminDashboardActionControlPlane::ACKNOWLEDGE_BLOCKED_STATE_ACTION]) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_ACTIONS_INVALID');
        }

        $projectionPayload = $projection->toArray();
        if (($projectionPayload['surface'] ?? null) !== AdminDashboardActionAuditProjection::SURFACE) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_SURFACE_INVALID');
        }

        if (($projectionPayload['kind'] ?? null) !== AdminDashboardActionAuditProjection::KIND) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_KIND_INVALID');
        }

        $publicResponse = $denied->publicResponse();
        if ($publicResponse === null) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_PUBLIC_RESPONSE_MISSING');
        }

        $publicBody = $publicResponse->body();
        foreach (['OPUS-ADM-AUD', 'action_audit_projection', 'ADMIN_DASHBOARD_ACTION_SCOPE_DENIED', 'ADMIN_ACKNOWLEDGE_BLOCKED_STATE', 'blocked_state_acknowledged', 'local_admin_projection_missing_scope_smoke'] as $forbiddenLeak) {
            if (str_contains($publicBody, $forbiddenLeak)) {
                throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_PUBLIC_LEAK: ' . $forbiddenLeak);
            }
        }

        return [
            'ok' => true,
            'gate' => 'P117A10_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_SMOKE',
            'projection_surface' => $projectionPayload['surface'],
            'projection_kind' => $projectionPayload['kind'],
            'projection_event_count' => $projection->count(),
            'projection_first_decision' => $decisions[0],
            'projection_second_decision' => $decisions[1],
            'projection_first_action' => $actions[0],
            'projection_second_action' => $actions[1],
            'denied_public_status' => $publicResponse->statusCode(),
            'denied_is_public_response' => true,
            'denied_public_body' => $publicBody,
        ];
    }
}
