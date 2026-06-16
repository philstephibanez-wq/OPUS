<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Admin\AdminBlockedStatesDashboardResponseRenderer;
use Opus\Admin\AdminBlockedStatesDashboardRoute;
use Opus\Admin\AdminDashboardResponse;
use Opus\Admin\AdminDashboardRouteControlPlane;
use Opus\Admin\AdminRouteRequest;
use Opus\Admin\AdminServerOverviewAccessControlPlane;
use Opus\Admin\AdminServerOverviewDashboardRoute;
use Opus\Http\PublicRequest;
use Opus\Http\PublicResponse;
use Opus\Security\PublicBlockedResponseRenderer;
use Opus\Security\PublicRouteControlPlane;
use Opus\Server\ServerSiteRegistry;
use Opus\Server\ServerSiteSupervisor;
use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Bridge the native OPUS HTTP entrypoint to protected administrator routes
 *   without bypassing their route-specific control planes.
 *
 * Responsibility:
 *   Convert HTTP server facts into explicit OPUS public/admin request objects and
 *   return either a protected admin dashboard response or the opaque public block
 *   response.
 *
 * Contract:
 *   Only the loopback-local admin preview may receive bootstrap admin scopes
 *   until OPUS Identity Authority replaces this temporary identity. Every
 *   non-local or malformed admin route request is denied through the matching
 *   admin control plane and rendered as the opaque public support response.
 */
final class NativeHttpKernel
{
    private const LOCAL_ADMIN_IDENTITY = 'local_admin_http_preview';
    private const DEMO_BLOCKED_PATH = '/missing';

    /** @param array<string,mixed> $server */
    public function handle(array $server): AdminDashboardResponse|PublicResponse
    {
        $method = $this->requiredServerString($server, 'REQUEST_METHOD');
        $uri = $this->requiredServerString($server, 'REQUEST_URI');
        $site = $this->siteFromHost($this->requiredServerString($server, 'HTTP_HOST'));
        $path = $this->pathFromUri($uri);

        if ($path === AdminDashboardRouteControlPlane::BLOCKED_STATES_PATH) {
            return $this->handleBlockedStatesDashboard($method, $path, $site, $server);
        }

        if ($path === AdminServerOverviewAccessControlPlane::SERVER_OVERVIEW_PATH) {
            return $this->handleServerOverviewDashboard($method, $path, $site, $server);
        }

        return $this->renderUnknownPublicRoute($method, $path, $site);
    }

    /** @param array<string,mixed> $server */
    private function handleBlockedStatesDashboard(
        string $method,
        string $path,
        string $site,
        array $server
    ): AdminDashboardResponse|PublicResponse {
        $blockedEvent = (new PublicRouteControlPlane())
            ->denyUnknownRoute(PublicRequest::get(self::DEMO_BLOCKED_PATH, $site))
            ->blockedStateEvent();

        if ($blockedEvent === null) {
            throw new RuntimeException('OPUS_NATIVE_HTTP_ADMIN_DASHBOARD_SOURCE_EVENT_MISSING');
        }

        $adminRequest = $this->isLoopbackLocal($server)
            ? AdminRouteRequest::adminGet(
                $path,
                $site,
                self::LOCAL_ADMIN_IDENTITY,
                [AdminDashboardRouteControlPlane::REQUIRED_SCOPE]
            )
            : AdminRouteRequest::anonymousGet($path, $site);

        if (strtoupper($method) !== 'GET') {
            $adminRequest = new AdminRouteRequest($method, $path, $site, 'non_get_admin_dashboard_request', ['admin'], [AdminDashboardRouteControlPlane::REQUIRED_SCOPE]);
        }

        $decision = (new AdminBlockedStatesDashboardRoute())->handle($adminRequest, $blockedEvent);

        return (new AdminBlockedStatesDashboardResponseRenderer())->render($decision);
    }

    /** @param array<string,mixed> $server */
    private function handleServerOverviewDashboard(
        string $method,
        string $path,
        string $site,
        array $server
    ): AdminDashboardResponse|PublicResponse {
        $adminRequest = $this->isLoopbackLocal($server)
            ? AdminRouteRequest::adminGet(
                $path,
                $site,
                self::LOCAL_ADMIN_IDENTITY,
                [AdminServerOverviewAccessControlPlane::REQUIRED_SCOPE]
            )
            : AdminRouteRequest::anonymousGet($path, $site);

        if (strtoupper($method) !== 'GET') {
            $adminRequest = new AdminRouteRequest($method, $path, $site, 'non_get_admin_server_overview_request', ['admin'], [AdminServerOverviewAccessControlPlane::REQUIRED_SCOPE]);
        }

        $opusRoot = dirname(__DIR__, 3);
        $registry = ServerSiteRegistry::fromConfigFile($opusRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'opus_server_sites.php');
        $snapshot = (new ServerSiteSupervisor($registry))->snapshot($site);

        return (new AdminServerOverviewDashboardRoute())->handle($adminRequest, $snapshot);
    }

    private function renderUnknownPublicRoute(string $method, string $path, string $site): PublicResponse
    {
        $request = new PublicRequest($method, $path, $site);
        $blockedEvent = (new PublicRouteControlPlane())->denyUnknownRoute($request)->blockedStateEvent();

        return (new PublicBlockedResponseRenderer())->render($blockedEvent);
    }

    /** @param array<string,mixed> $server */
    private function requiredServerString(array $server, string $key): string
    {
        $value = $server[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('OPUS_NATIVE_HTTP_SERVER_FIELD_MISSING_' . $key);
        }

        return $value;
    }

    private function siteFromHost(string $host): string
    {
        $site = trim($host);
        if ($site === '') {
            throw new RuntimeException('OPUS_NATIVE_HTTP_SITE_EMPTY');
        }

        return $site;
    }

    private function pathFromUri(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path[0] !== '/') {
            throw new RuntimeException('OPUS_NATIVE_HTTP_URI_PATH_INVALID');
        }

        return $path;
    }

    /** @param array<string,mixed> $server */
    private function isLoopbackLocal(array $server): bool
    {
        $remoteAddress = $server['REMOTE_ADDR'] ?? null;
        if (!is_string($remoteAddress) || $remoteAddress === '') {
            return false;
        }

        return in_array($remoteAddress, ['127.0.0.1', '::1'], true);
    }
}