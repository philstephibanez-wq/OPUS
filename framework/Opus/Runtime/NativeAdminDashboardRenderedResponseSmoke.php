<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Admin\AdminBlockedStatesDashboardResponseRenderer;
use Opus\Admin\AdminBlockedStatesDashboardRoute;
use Opus\Admin\AdminDashboardResponse;
use Opus\Admin\AdminDashboardRouteControlPlane;
use Opus\Admin\AdminRouteRequest;
use Opus\Http\PublicRequest;
use Opus\Http\PublicResponse;
use Opus\Security\PublicRouteControlPlane;
use RuntimeException;

final class NativeAdminDashboardRenderedResponseSmoke
{
    /** @return array<string,mixed> */
    public static function run(): array
    {
        $event = (new PublicRouteControlPlane())
            ->denyUnknownRoute(PublicRequest::get('/missing', 'opus-demo'))
            ->blockedStateEvent();

        if ($event === null) {
            throw new RuntimeException('OPUS_ADMIN_RENDER_SMOKE_EVENT_MISSING');
        }

        $route = new AdminBlockedStatesDashboardRoute();
        $renderer = new AdminBlockedStatesDashboardResponseRenderer();

        $adminDecision = $route->handle(
            AdminRouteRequest::adminGet(
                AdminDashboardRouteControlPlane::BLOCKED_STATES_PATH,
                'opus-demo',
                'admin-local-smoke',
                [AdminDashboardRouteControlPlane::REQUIRED_SCOPE]
            ),
            $event
        );

        $adminResponse = $renderer->render($adminDecision);
        if (!$adminResponse instanceof AdminDashboardResponse) {
            throw new RuntimeException('OPUS_ADMIN_RENDER_SMOKE_ADMIN_RESPONSE_INVALID');
        }

        $adminBody = $adminResponse->body();
        if (!str_contains($adminBody, 'admin_dashboard') || !str_contains($adminBody, 'PUBLIC_REQUEST_BLOCKED')) {
            throw new RuntimeException('OPUS_ADMIN_RENDER_SMOKE_ADMIN_BODY_INVALID');
        }

        if (str_contains($adminBody, 'Contactez le support.')) {
            throw new RuntimeException('OPUS_ADMIN_RENDER_SMOKE_PUBLIC_TEXT_IN_ADMIN_BODY');
        }

        $deniedDecision = $route->handle(
            AdminRouteRequest::anonymousGet(AdminDashboardRouteControlPlane::BLOCKED_STATES_PATH, 'opus-demo'),
            $event
        );

        $deniedResponse = $renderer->render($deniedDecision);
        if (!$deniedResponse instanceof PublicResponse) {
            throw new RuntimeException('OPUS_ADMIN_RENDER_SMOKE_DENIED_RESPONSE_INVALID');
        }

        $deniedBody = $deniedResponse->body();
        if (str_contains($deniedBody, 'admin_dashboard') || str_contains($deniedBody, 'PUBLIC_REQUEST_BLOCKED')) {
            throw new RuntimeException('OPUS_ADMIN_RENDER_SMOKE_PUBLIC_LEAK');
        }

        return [
            'ok' => true,
            'gate' => 'P117A6_NATIVE_ADMIN_DASHBOARD_RENDERED_RESPONSE_SMOKE',
            'admin_status' => $adminResponse->statusCode(),
            'admin_content_type' => $adminResponse->headers()['Content-Type'] ?? '<missing>',
            'admin_surface_header' => $adminResponse->headers()['X-OPUS-Admin-Surface'] ?? '<missing>',
            'admin_body_contains_dashboard' => str_contains($adminBody, 'admin_dashboard'),
            'admin_body_contains_blocked_state' => str_contains($adminBody, 'PUBLIC_REQUEST_BLOCKED'),
            'denied_status' => $deniedResponse->statusCode(),
            'denied_public_body' => $deniedBody,
            'denied_is_public_response' => $deniedResponse instanceof PublicResponse,
        ];
    }
}
