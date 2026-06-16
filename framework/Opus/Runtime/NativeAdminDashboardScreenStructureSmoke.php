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

final class NativeAdminDashboardScreenStructureSmoke
{
    /** @return array<string,mixed> */
    public static function run(): array
    {
        $event = (new PublicRouteControlPlane())
            ->denyUnknownRoute(PublicRequest::get('/missing', 'opus-demo'))
            ->blockedStateEvent();

        if ($event === null) {
            throw new RuntimeException('OPUS_ADMIN_SCREEN_SMOKE_EVENT_MISSING');
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
            throw new RuntimeException('OPUS_ADMIN_SCREEN_SMOKE_ADMIN_RESPONSE_INVALID');
        }

        $adminBody = $adminResponse->body();
        foreach ([
            'data-opus-screen="blocked-states"',
            'data-opus-region="admin_header"',
            'data-opus-region="blocked_state_summary"',
            'data-opus-region="blocked_state_detail"',
            'data-opus-region="recommended_actions"',
            'data-opus-region="admin_audit_footer"',
            'ADMIN_ACKNOWLEDGE_BLOCKED_STATE_EVENT',
        ] as $needle) {
            if (!str_contains($adminBody, $needle)) {
                throw new RuntimeException('OPUS_ADMIN_SCREEN_SMOKE_ADMIN_BODY_MISSING: ' . $needle);
            }
        }

        if (str_contains($adminBody, 'Contactez le support.')) {
            throw new RuntimeException('OPUS_ADMIN_SCREEN_SMOKE_PUBLIC_TEXT_IN_ADMIN_BODY');
        }

        $deniedDecision = $route->handle(
            AdminRouteRequest::anonymousGet(AdminDashboardRouteControlPlane::BLOCKED_STATES_PATH, 'opus-demo'),
            $event
        );

        $deniedResponse = $renderer->render($deniedDecision);
        if (!$deniedResponse instanceof PublicResponse) {
            throw new RuntimeException('OPUS_ADMIN_SCREEN_SMOKE_DENIED_RESPONSE_INVALID');
        }

        $deniedBody = $deniedResponse->body();
        if (str_contains($deniedBody, 'admin_header') || str_contains($deniedBody, 'PUBLIC_REQUEST_BLOCKED')) {
            throw new RuntimeException('OPUS_ADMIN_SCREEN_SMOKE_PUBLIC_LEAK');
        }

        return [
            'ok' => true,
            'gate' => 'P117A7_NATIVE_ADMIN_DASHBOARD_SCREEN_STRUCTURE_SMOKE',
            'admin_status' => $adminResponse->statusCode(),
            'admin_screen_header' => $adminResponse->headers()['X-OPUS-Admin-Screen'] ?? '<missing>',
            'screen_has_header_region' => str_contains($adminBody, 'data-opus-region="admin_header"'),
            'screen_has_summary_region' => str_contains($adminBody, 'data-opus-region="blocked_state_summary"'),
            'screen_has_detail_region' => str_contains($adminBody, 'data-opus-region="blocked_state_detail"'),
            'screen_has_actions_region' => str_contains($adminBody, 'data-opus-region="recommended_actions"'),
            'screen_has_footer_region' => str_contains($adminBody, 'data-opus-region="admin_audit_footer"'),
            'denied_status' => $deniedResponse->statusCode(),
            'denied_is_public_response' => $deniedResponse instanceof PublicResponse,
            'denied_public_body' => $deniedBody,
        ];
    }
}
