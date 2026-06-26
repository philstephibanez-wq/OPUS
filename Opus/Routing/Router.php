<?php
declare(strict_types=1);

namespace Opus\Routing;

use Opus\Runtime\Kernel;

use Opus\View\View;

use Opus\FSM\Fsm;
use Opus\Security\Acl;
use Opus\Application\ApplicationDefinition;
use Opus\Foundation\Support;

use Opus\Http\Response;

use Opus\Http\Request;

/**
 * Runtime router for integrated OPUS applications.
 *
 * Resolves page routes and API endpoints from application definitions, applies access control and delegates HTML rendering to the view layer.
 */
final class Router
 implements RouterInterface {
    private Kernel $kernel;
    private View $view;
    private Acl $acl;
    private Fsm $fsm;

    public function __construct(Kernel $kernel, View $view, Acl $acl, Fsm $fsm)
    {
        $this->kernel = $kernel;
        $this->view = $view;
        $this->acl = $acl;
        $this->fsm = $fsm;
    }

    /** @param list<string> $segments */
    public function dispatch(ApplicationDefinition $application, array $segments, Request $request): Response
    {
        $routes = $application->routes();

        if (($segments[0] ?? '') === 'api') {
            return $this->dispatchApi($application, array_slice($segments, 1), $request);
        }

        $lang = $segments[0] ?? $application->defaultLang;
        if (!$application->hasLanguage($lang)) {
            $lang = $application->defaultLang;
            $routeSegments = $segments;
        } else {
            $routeSegments = array_slice($segments, 1);
        }

        $routeKey = implode('/', $routeSegments);
        $langRoutes = (array)($routes[$lang] ?? []);
        $pageId = (string)($langRoutes[$routeKey] ?? '');

        if ($pageId === '') {
            return $this->notFound($application, $lang, $request, $routeKey);
        }

        $content = $application->content();
        $page = (array)($content[$lang][$pageId] ?? []);
        if (!$page) {
            throw new \RuntimeException("Page content missing: {$application->slug}/{$lang}/{$pageId}");
        }

        if (!$this->acl->canView($page)) {
            return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
        }

        if ($pageId === 'fsm') {
            $page['flow'] = $this->fsm->demoFlow($lang);
        }

        return Response::html($this->view->render($application, $lang, $pageId, $page));
    }

    /** @param list<string> $segments */
    private function dispatchApi(ApplicationDefinition $application, array $segments, Request $request): Response
    {
        $endpoint = implode('/', $segments);
        if ($application->slug === 'demo' && $endpoint === 'ping') {
            return Response::json([
                'ok' => true,
                'application' => $application->slug,
                'host' => $request->host,
                'base_path' => $request->basePath,
                'path' => $request->path,
                'time' => date(DATE_ATOM),
            ]);
        }

        if ($application->slug === 'demo' && $endpoint === 'site') {
            return Response::json([
                'application' => $application->slug,
                'name' => $application->name,
                'languages' => $application->languages,
                'paths' => [
                    'application_dir' => $application->dir,
                    'www' => $application->dir . '/www',
                    'logs' => $application->dir . '/logs',
                    'tmp' => $application->dir . '/tmp',
                    'history' => $application->dir . '/history',
                ],
                'checks' => [
                    'dynamic_paths' => true,
                    'external_links_required' => false,
                    'accented_url' => $this->kernel->applicationUrl('demo', 'démo-interne', 'fr'),
                ],
            ]);
        }

        return Response::json([
            'ok' => false,
            'error' => 'Unknown API endpoint',
            'endpoint' => $endpoint,
        ], 404);
    }

    private function notFound(ApplicationDefinition $application, string $lang, Request $request, string $routeKey): Response
    {
        $route = Support::e($routeKey);
        $path = Support::e($request->path);
        $home = $this->kernel->applicationUrl($application->slug, '', $lang);
        return Response::html("<!doctype html><html lang=\"{$lang}\"><head><meta charset=\"utf-8\"><title>404</title><link rel=\"stylesheet\" href=\"" . $this->kernel->assetUrl($application, 'assets/css/site.css') . "\"></head><body><main class=\"shell\"><section class=\"panel\"><h1>DISPATCH erreur 404</h1><p>Path: {$path}</p><p>Route: {$route}</p><p><a href=\"{$home}\">Retour application</a></p></section></main></body></html>", 404);
    }
}
