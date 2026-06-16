<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Admin\AdminDashboardResponse;
use Opus\Admin\AdminServerOverviewAccessControlPlane;
use Opus\Http\PublicResponse;
use Opus\Server\ServerSiteRegistry;
use RuntimeException;

/**
 * INTERNAL SMOKE
 *
 * Role:
 *   Prove that the native OPUS dashboard can supervise every declared site of
 *   the server through the server control-plane route.
 *
 * Contract:
 *   This smoke is local runtime validation only. It must not create product-root
 *   documentation files and must not weaken public opaque responses.
 */
final class NativeServerOverviewDashboardSmoke
{
    /** @return array<string,mixed> */
    public static function run(): array
    {
        $opusRoot = dirname(__DIR__, 3);
        $registry = ServerSiteRegistry::fromConfigFile($opusRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'opus_server_sites.php');

        $kernel = new NativeHttpKernel();
        $allowed = $kernel->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => AdminServerOverviewAccessControlPlane::SERVER_OVERVIEW_PATH,
            'HTTP_HOST' => 'opus.localhost',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        if (!$allowed instanceof AdminDashboardResponse) {
            throw new RuntimeException('OPUS_SERVER_OVERVIEW_ALLOWED_RESPONSE_INVALID');
        }

        $allowedBody = $allowed->body();
        foreach ([
            'data-opus-dashboard="server-overview"',
            'data-opus-region="server_summary"',
            'data-opus-region="server_sites"',
            'OPUS Server Control Plane',
            'logandplay.localhost',
            'demo.logandplay.localhost',
            'maestro.logandplay.localhost',
            'ADMIN_SERVER_OVERVIEW_READ',
        ] as $requiredHtml) {
            if (!str_contains($allowedBody, $requiredHtml)) {
                throw new RuntimeException('OPUS_SERVER_OVERVIEW_HTML_MISSING: ' . $requiredHtml);
            }
        }

        $denied = $kernel->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => AdminServerOverviewAccessControlPlane::SERVER_OVERVIEW_PATH,
            'HTTP_HOST' => 'evil.example.invalid',
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        if (!$denied instanceof PublicResponse) {
            throw new RuntimeException('OPUS_SERVER_OVERVIEW_DENIED_RESPONSE_INVALID');
        }

        $deniedBody = $denied->body();
        foreach (['OPUS Server Control Plane', 'ADMIN_VIEW_SERVER_OVERVIEW', 'ADMIN_SERVER_OVERVIEW_ROLE_DENIED', 'data-opus-dashboard'] as $forbiddenLeak) {
            if (str_contains($deniedBody, $forbiddenLeak)) {
                throw new RuntimeException('OPUS_SERVER_OVERVIEW_PUBLIC_LEAK: ' . $forbiddenLeak);
            }
        }

        return [
            'ok' => true,
            'gate' => 'P117A14_MULTISITE_SERVER_CONTROL_PLANE_SMOKE',
            'registry_count' => $registry->count(),
            'allowed_status' => $allowed->statusCode(),
            'allowed_content_type' => $allowed->headers()['Content-Type'] ?? '',
            'has_server_overview' => str_contains($allowedBody, 'data-opus-dashboard="server-overview"'),
            'has_sites_region' => str_contains($allowedBody, 'data-opus-region="server_sites"'),
            'has_logandplay_site' => str_contains($allowedBody, 'logandplay.localhost'),
            'has_demo_site' => str_contains($allowedBody, 'demo.logandplay.localhost'),
            'has_maestro_site' => str_contains($allowedBody, 'maestro.logandplay.localhost'),
            'denied_status' => $denied->statusCode(),
            'denied_is_public_response' => true,
            'denied_public_body' => $deniedBody,
        ];
    }
}