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
 *   Prove that the native administrator dashboard is rendered as an OPUS-styled
 *   HTTP site surface, without weakening the public opaque response contract.
 *
 * Contract:
 *   This smoke is a runtime validation helper. It must not be routed publicly and
 *   must not create product-root documentation files.
 */
final class NativeAdminDashboardSiteStyleSmoke
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
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_SITE_STYLE_ALLOWED_RESPONSE_INVALID');
        }

        $allowedBody = $allowed->body();
        foreach ([
            '<meta name="viewport" content="width=device-width, initial-scale=1">',
            'data-opus-admin-style="native"',
            'class="opus-admin-shell"',
            'class="opus-admin-hero"',
            'class="opus-admin-card opus-admin-card--summary"',
            'class="opus-admin-action-list"',
            'data-opus-region="admin_header"',
            'data-opus-region="blocked_state_summary"',
            'data-opus-region="blocked_state_detail"',
            'data-opus-region="recommended_actions"',
            'data-opus-region="admin_audit_footer"',
        ] as $requiredHtml) {
            if (!str_contains($allowedBody, $requiredHtml)) {
                throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_SITE_STYLE_HTML_MISSING: ' . $requiredHtml);
            }
        }

        $denied = $kernel->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => AdminDashboardRouteControlPlane::BLOCKED_STATES_PATH,
            'HTTP_HOST' => 'example.invalid',
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        if (!$denied instanceof PublicResponse) {
            throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_SITE_STYLE_DENIED_RESPONSE_INVALID');
        }

        $deniedBody = $denied->body();
        foreach (['opus-admin-shell', 'data-opus-admin-style', 'ADMIN_DASHBOARD_ROLE_DENIED', 'ADMIN_VIEW_BLOCKED_STATES'] as $forbiddenLeak) {
            if (str_contains($deniedBody, $forbiddenLeak)) {
                throw new RuntimeException('OPUS_NATIVE_ADMIN_DASHBOARD_SITE_STYLE_PUBLIC_LEAK: ' . $forbiddenLeak);
            }
        }

        return [
            'ok' => true,
            'gate' => 'P117A12_NATIVE_ADMIN_DASHBOARD_SITE_STYLE_SMOKE',
            'allowed_status' => $allowed->statusCode(),
            'allowed_content_type' => $allowed->headers()['Content-Type'] ?? '',
            'has_native_style' => str_contains($allowedBody, 'data-opus-admin-style="native"'),
            'has_site_shell' => str_contains($allowedBody, 'class="opus-admin-shell"'),
            'has_hero' => str_contains($allowedBody, 'class="opus-admin-hero"'),
            'has_cards' => str_contains($allowedBody, 'class="opus-admin-card'),
            'denied_status' => $denied->statusCode(),
            'denied_is_public_response' => true,
            'denied_public_body' => $deniedBody,
        ];
    }
}
