<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Admin\AdminDashboardActionControlPlane;
use Opus\Admin\AdminDashboardActionRequest;
use Opus\Http\PublicRequest;
use Opus\Security\PublicRouteControlPlane;
use RuntimeException;

final class NativeAdminDashboardActionControlSmoke
{
    /** @return array<string,mixed> */
    public static function run(): array
    {
        $publicRequest = PublicRequest::get('/missing', 'opus-demo');
        $event = (new PublicRouteControlPlane())->denyUnknownRoute($publicRequest)->blockedStateEvent();
        if ($event === null) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_SMOKE_SOURCE_EVENT_MISSING');
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
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_ALLOWED_REQUEST_DENIED');
        }

        if ($allowed->effect() !== 'blocked_state_acknowledged') {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_EFFECT_INVALID');
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
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_DENIED_REQUEST_GRANTED');
        }

        $publicResponse = $denied->publicResponse();
        if ($publicResponse === null) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_DENIED_PUBLIC_RESPONSE_MISSING');
        }

        $publicBody = $publicResponse->body();
        foreach (['ADMIN_DASHBOARD_ACTION_BLOCKED', 'ADMIN_DASHBOARD_ACTION_SCOPE_DENIED', 'ADMIN_REVIEW_DASHBOARD_ACTION', 'ADMIN_ACKNOWLEDGE_BLOCKED_STATE', 'admin_dashboard', 'local_admin_missing_scope_smoke'] as $forbiddenLeak) {
            if (str_contains($publicBody, $forbiddenLeak)) {
                throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_PUBLIC_LEAK: ' . $forbiddenLeak);
            }
        }

        $deniedReason = $denied->adminDiagnostics()['reason'] ?? '<missing>';
        if ($deniedReason !== 'ADMIN_DASHBOARD_ACTION_SCOPE_DENIED') {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ACTION_DENIED_REASON_INVALID');
        }

        return [
            'ok' => true,
            'gate' => 'P117A8_NATIVE_ADMIN_DASHBOARD_ACTION_CONTROL_SMOKE',
            'allowed_action' => $allowed->action(),
            'allowed_granted' => $allowed->isGranted(),
            'allowed_effect' => $allowed->effect(),
            'denied_granted' => $denied->isGranted(),
            'denied_reason' => $deniedReason,
            'denied_public_status' => $publicResponse->statusCode(),
            'denied_is_public_response' => true,
            'denied_public_body' => $publicBody,
        ];
    }
}
