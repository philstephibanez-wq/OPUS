<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Admin\AdminDashboardActionAuditTrail;
use Opus\Admin\AdminDashboardActionControlPlane;
use Opus\Admin\AdminDashboardActionRequest;
use Opus\Http\PublicRequest;
use Opus\Security\PublicRouteControlPlane;
use RuntimeException;

final class NativeAdminDashboardActionAuditSmoke
{
    /** @return array<string,mixed> */
    public static function run(): array
    {
        $publicRequest = PublicRequest::get('/missing', 'opus-demo');
        $event = (new PublicRouteControlPlane())->denyUnknownRoute($publicRequest)->blockedStateEvent();
        if ($event === null) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_SOURCE_EVENT_MISSING');
        }

        $controlPlane = new AdminDashboardActionControlPlane();
        $allowed = $controlPlane->authorizeAcknowledgeBlockedState(
            AdminDashboardActionRequest::admin(
                AdminDashboardActionControlPlane::ACKNOWLEDGE_BLOCKED_STATE_ACTION,
                'opus-demo',
                'local_admin_smoke',
                [AdminDashboardActionControlPlane::ACKNOWLEDGE_BLOCKED_STATE_SCOPE]
            ),
            $event
        );

        if (!$allowed->isGranted()) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_ALLOWED_REQUEST_DENIED');
        }

        $denied = $controlPlane->authorizeAcknowledgeBlockedState(
            AdminDashboardActionRequest::admin(
                AdminDashboardActionControlPlane::ACKNOWLEDGE_BLOCKED_STATE_ACTION,
                'opus-demo',
                'local_admin_missing_scope_smoke',
                []
            ),
            $event
        );

        if ($denied->isGranted()) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_DENIED_REQUEST_GRANTED');
        }

        $trail = new AdminDashboardActionAuditTrail();
        $allowedAudit = $trail->record($allowed);
        $deniedAudit = $trail->record($denied);

        if ($trail->count() !== 2) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_COUNT_INVALID');
        }

        if ($allowedAudit->decision() !== 'ALLOW') {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_ALLOWED_DECISION_INVALID');
        }

        if ($allowedAudit->action() !== AdminDashboardActionControlPlane::ACKNOWLEDGE_BLOCKED_STATE_ACTION) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_ALLOWED_ACTION_INVALID');
        }

        if ($allowedAudit->effect() !== 'blocked_state_acknowledged') {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_ALLOWED_EFFECT_INVALID');
        }

        if ($deniedAudit->decision() !== 'DENY') {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_DENIED_DECISION_INVALID');
        }

        if ($deniedAudit->reason() !== 'ADMIN_DASHBOARD_ACTION_SCOPE_DENIED') {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_DENIED_REASON_INVALID');
        }

        if ($deniedAudit->publicUserMessagePolicy() !== 'opaque_support_only') {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PUBLIC_POLICY_INVALID');
        }

        $publicResponse = $denied->publicResponse();
        if ($publicResponse === null) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PUBLIC_RESPONSE_MISSING');
        }

        $publicBody = $publicResponse->body();
        foreach (['OPUS-ADM-AUD', 'ADMIN_DASHBOARD_ACTION_SCOPE_DENIED', 'ADMIN_ACKNOWLEDGE_BLOCKED_STATE', 'blocked_state_acknowledged', 'local_admin_missing_scope_smoke'] as $forbiddenLeak) {
            if (str_contains($publicBody, $forbiddenLeak)) {
                throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_PUBLIC_LEAK: ' . $forbiddenLeak);
            }
        }

        return [
            'ok' => true,
            'gate' => 'P117A9_NATIVE_ADMIN_DASHBOARD_ACTION_AUDIT_SMOKE',
            'audit_event_count' => $trail->count(),
            'allowed_audit_decision' => $allowedAudit->decision(),
            'allowed_audit_action' => $allowedAudit->action(),
            'allowed_audit_effect' => $allowedAudit->effect(),
            'denied_audit_decision' => $deniedAudit->decision(),
            'denied_audit_reason' => $deniedAudit->reason(),
            'denied_public_status' => $publicResponse->statusCode(),
            'denied_is_public_response' => true,
            'denied_public_body' => $publicBody,
        ];
    }
}
