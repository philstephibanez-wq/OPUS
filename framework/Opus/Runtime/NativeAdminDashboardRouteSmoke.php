<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Admin\AdminBlockedStatesDashboardRoute;
use Opus\Admin\AdminDashboardRouteControlPlane;
use Opus\Admin\AdminRouteRequest;
use Opus\Http\PublicRequest;
use Opus\Security\PublicRouteControlPlane;
use RuntimeException;

final class NativeAdminDashboardRouteSmoke
{
    /** @return array<string,mixed> */
    public static function run(): array
    {
        $publicRequest = PublicRequest::get('/missing', 'opus-demo');
        $event = (new PublicRouteControlPlane())->denyUnknownRoute($publicRequest)->blockedStateEvent();
        if ($event === null) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_SMOKE_SOURCE_EVENT_MISSING');
        }

        $route = new AdminBlockedStatesDashboardRoute();
        $authorized = $route->handle(
            AdminRouteRequest::adminGet(
                AdminDashboardRouteControlPlane::BLOCKED_STATES_PATH,
                'opus-demo',
                'admin:local-smoke',
                [AdminDashboardRouteControlPlane::REQUIRED_SCOPE]
            ),
            $event
        );

        if (!$authorized->isAllowed()) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_AUTHORIZED_ROUTE_DENIED');
        }

        $adminViewModel = $authorized->adminViewModel()?->toArray();
        if (!is_array($adminViewModel)) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_VIEWMODEL_MISSING');
        }

        $anonymousDenied = $route->handle(
            AdminRouteRequest::anonymousGet(AdminDashboardRouteControlPlane::BLOCKED_STATES_PATH, 'opus-demo'),
            $event
        );

        if ($anonymousDenied->isAllowed()) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_ANONYMOUS_ALLOWED');
        }

        $publicResponse = $anonymousDenied->publicResponse();
        if ($publicResponse === null) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_DENIED_PUBLIC_RESPONSE_MISSING');
        }

        $publicBody = $publicResponse->body();
        foreach (['ADMIN_DASHBOARD_ACCESS_BLOCKED', 'ADMIN_DASHBOARD_ROLE_DENIED', 'ADMIN_REVIEW_DASHBOARD_ACCESS', 'admin_dashboard', 'admin:local-smoke'] as $forbiddenLeak) {
            if (str_contains($publicBody, $forbiddenLeak)) {
                throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_PUBLIC_LEAK: ' . $forbiddenLeak);
            }
        }

        foreach (['surface', 'kind', 'blocked_state', 'reason', 'admin_action', 'public_user_message_policy'] as $requiredAdminKey) {
            if (!array_key_exists($requiredAdminKey, $adminViewModel)) {
                throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_FIELD_MISSING: ' . $requiredAdminKey);
            }
        }

        return [
            'ok' => true,
            'gate' => 'P117A5_NATIVE_ADMIN_DASHBOARD_ROUTE_SMOKE',
            'admin_route' => AdminDashboardRouteControlPlane::BLOCKED_STATES_PATH,
            'admin_allowed' => $authorized->isAllowed(),
            'admin_surface' => $adminViewModel['surface'],
            'admin_blocked_state' => $adminViewModel['blocked_state'],
            'admin_reason' => $adminViewModel['reason'],
            'anonymous_allowed' => $anonymousDenied->isAllowed(),
            'anonymous_public_status' => $publicResponse->statusCode(),
            'anonymous_public_body' => $publicBody,
            'anonymous_admin_reason' => $anonymousDenied->adminDiagnostics()['reason'] ?? '<missing>',
        ];
    }
}
