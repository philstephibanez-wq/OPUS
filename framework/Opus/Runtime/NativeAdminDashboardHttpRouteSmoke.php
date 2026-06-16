<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Admin\AdminDashboardResponse;
use Opus\Admin\AdminDashboardRouteControlPlane;
use Opus\Http\PublicResponse;
use RuntimeException;

/**
 * INTERNAL SMOKE
 *
 * Role:
 *   Prove that the native admin dashboard is reachable through the OPUS HTTP
 *   runtime kernel as a real route response.
 *
 * Contract:
 *   This smoke is a runtime validation helper. It must not be routed publicly and
 *   must not create product-root documentation files.
 */
final class NativeAdminDashboardHttpRouteSmoke
{
    /** @return array<string,mixed> */
    public static function run(): array
    {
        $kernel = new NativeHttpKernel();

        $allowed = $kernel->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => AdminDashboardRouteControlPlane::BLOCKED_STATES_PATH,
            'HTTP_HOST' => '127.0.0.1:8765',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        if (!$allowed instanceof AdminDashboardResponse) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_HTTP_ROUTE_ALLOWED_RESPONSE_INVALID');
        }

        $allowedBody = $allowed->body();
        foreach (['data-opus-surface="admin_dashboard"', 'data-opus-dashboard="blocked-states"', 'data-opus-region="admin_header"', 'data-opus-region="blocked_state_summary"'] as $requiredHtml) {
            if (!str_contains($allowedBody, $requiredHtml)) {
                throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_HTTP_ROUTE_ALLOWED_HTML_MISSING: ' . $requiredHtml);
            }
        }

        $denied = $kernel->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => AdminDashboardRouteControlPlane::BLOCKED_STATES_PATH,
            'HTTP_HOST' => 'example.invalid',
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        if (!$denied instanceof PublicResponse) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_HTTP_ROUTE_DENIED_RESPONSE_INVALID');
        }

        $deniedBody = $denied->body();
        foreach (['ADMIN_DASHBOARD_ROLE_DENIED', 'ADMIN_DASHBOARD_SCOPE_DENIED', 'ADMIN_VIEW_BLOCKED_STATES', 'local_admin_http_preview', 'OPUS-ADM-'] as $forbiddenLeak) {
            if (str_contains($deniedBody, $forbiddenLeak)) {
                throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_HTTP_ROUTE_PUBLIC_LEAK: ' . $forbiddenLeak);
            }
        }

        return [
            'ok' => true,
            'gate' => 'P117A11_NATIVE_ADMIN_DASHBOARD_HTTP_ROUTE_SMOKE',
            'allowed_status' => $allowed->statusCode(),
            'allowed_content_type' => $allowed->headers()['Content-Type'] ?? '',
            'allowed_surface' => $allowed->headers()['X-OPUS-Admin-Surface'] ?? '',
            'allowed_route' => $allowed->headers()['X-OPUS-Admin-Route'] ?? '',
            'allowed_body_contains_dashboard' => str_contains($allowedBody, 'OPUS Admin Dashboard'),
            'denied_status' => $denied->statusCode(),
            'denied_is_public_response' => true,
            'denied_public_body' => $deniedBody,
        ];
    }
}
