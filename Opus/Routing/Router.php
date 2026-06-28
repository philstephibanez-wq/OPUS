<?php
declare(strict_types=1);

namespace Opus\Routing;

use Opus\Api\ApiDispatcher;
use Opus\Runtime\Kernel;
use Opus\View\View;
use Opus\Security\Acl;
use Opus\Application\ApplicationDefinition;
use Opus\Foundation\Support;
use Opus\Http\Response;
use Opus\Http\Request;
use Opus\Profiler\Profiler;
use Opus\Fsm\Runtime\FsmRuntimeConfigLoader;

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
    private Profiler $profiler;
    private FsmRuntimeConfigLoader $fsmRuntimeConfigLoader;

    public function __construct(Kernel $kernel, View $view, Acl $acl, Profiler $profiler, FsmRuntimeConfigLoader $fsmRuntimeConfigLoader)
    {
        $this->kernel = $kernel;
        $this->view = $view;
        $this->acl = $acl;
        $this->profiler = $profiler;
        $this->fsmRuntimeConfigLoader = $fsmRuntimeConfigLoader;
    }

    /** @param list<string> $segments */
    public function dispatch(ApplicationDefinition $application, array $segments, Request $request): Response
    {
        $this->profiler->event('routing', 'dispatch.start', ['application' => $application->slug, 'segments' => $segments]);
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
        $this->profiler->event('routing', 'route.resolved', ['lang' => $lang, 'route' => $routeKey, 'page_id' => $pageId]);

        if ($pageId === '') {
            return $this->notFound($application, $lang, $request, $routeKey);
        }

        $content = $application->content();
        $page = (array)($content[$lang][$pageId] ?? []);
        if (!$page) {
            throw new \RuntimeException("Page content missing: {$application->slug}/{$lang}/{$pageId}");
        }

        if (!$this->acl->canView($page)) {
            $this->profiler->event('routing', 'acl.forbidden', ['page_id' => $pageId]);
            return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
        }

        if ($pageId === 'fsm') {
            $page['flow'] = $this->fsmRuntimeConfigLoader->flowForDisplay('runtime_request');
            $this->profiler->event('runtime', 'fsm_runtime_config.loaded', ['id' => 'runtime_request']);
        }

        $this->profiler->event('template', 'view.render.start', ['page_id' => $pageId]);
        $html = $this->view->render($application, $lang, $pageId, $page);
        $this->profiler->event('template', 'view.render.done', ['page_id' => $pageId]);
        return Response::html($html);
    }

    /** @param list<string> $segments */
    private function dispatchApi(ApplicationDefinition $application, array $segments, Request $request): Response
    {
        $dispatcher = ApiDispatcher::fromProjectRoot(dirname(__DIR__, 2), $this->profiler, $this->fsmRuntimeConfigLoader);

        return $dispatcher->dispatch($application, $segments, $request);
    }

    private function notFound(ApplicationDefinition $application, string $lang, Request $request, string $routeKey): Response
    {
        $this->profiler->event('routing', 'route.not_found', ['path' => $request->path, 'route' => $routeKey]);
        $route = Support::e($routeKey);
        $path = Support::e($request->path);
        $home = $this->kernel->applicationUrl($application->slug, '', $lang);
        return Response::html("<!doctype html><html lang=\"{$lang}\"><head><meta charset=\"utf-8\"><title>404</title><link rel=\"stylesheet\" href=\"" . $this->kernel->assetUrl($application, 'assets/css/site.css') . "\"></head><body><main class=\"shell\"><section class=\"panel\"><h1>DISPATCH erreur 404</h1><p>Path: {$path}</p><p>Route: {$route}</p><p><a href=\"{$home}\">Retour application</a></p></section></main></body></html>", 404);
    }
}