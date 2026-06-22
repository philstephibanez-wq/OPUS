<?php
declare(strict_types=1);

namespace ASAP;

final class Router
{
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
    public function dispatch(Package $package, array $segments, Request $request): Response
    {
        $routes = $package->routes();

        if (($segments[0] ?? '') === 'api') {
            return $this->dispatchApi($package, array_slice($segments, 1), $request);
        }

        $lang = $segments[0] ?? $package->defaultLang;
        if (!$package->hasLanguage($lang)) {
            $lang = $package->defaultLang;
            $routeSegments = $segments;
        } else {
            $routeSegments = array_slice($segments, 1);
        }

        $routeKey = implode('/', $routeSegments);
        $langRoutes = (array)($routes[$lang] ?? []);
        $pageId = (string)($langRoutes[$routeKey] ?? '');

        if ($pageId === '') {
            return $this->notFound($package, $lang, $request, $routeKey);
        }

        $content = $package->content();
        $page = (array)($content[$lang][$pageId] ?? []);
        if (!$page) {
            throw new \RuntimeException("Page content missing: {$package->slug}/{$lang}/{$pageId}");
        }

        if (!$this->acl->canView($page)) {
            return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
        }

        if ($pageId === 'fsm') {
            $page['flow'] = $this->fsm->demoFlow($lang);
        }

        return Response::html($this->view->render($package, $lang, $pageId, $page));
    }

    /** @param list<string> $segments */
    private function dispatchApi(Package $package, array $segments, Request $request): Response
    {
        $endpoint = implode('/', $segments);
        if ($package->slug === 'demo' && $endpoint === 'ping') {
            return Response::json([
                'ok' => true,
                'package' => $package->slug,
                'host' => $request->host,
                'base_path' => $request->basePath,
                'path' => $request->path,
                'time' => date(DATE_ATOM),
            ]);
        }

        if ($package->slug === 'demo' && $endpoint === 'site') {
            return Response::json([
                'package' => $package->slug,
                'name' => $package->name,
                'languages' => $package->languages,
                'paths' => [
                    'package_dir' => $package->dir,
                    'www' => $package->dir . '/www',
                    'logs' => $package->dir . '/logs',
                    'tmp' => $package->dir . '/tmp',
                    'history' => $package->dir . '/history',
                ],
                'checks' => [
                    'dynamic_paths' => true,
                    'external_links_required' => false,
                    'accented_url' => $this->kernel->packageUrl('demo', 'démo-interne', 'fr'),
                ],
            ]);
        }

        return Response::json([
            'ok' => false,
            'error' => 'Unknown API endpoint',
            'endpoint' => $endpoint,
        ], 404);
    }

    private function notFound(Package $package, string $lang, Request $request, string $routeKey): Response
    {
        $route = Support::e($routeKey);
        $path = Support::e($request->path);
        $home = $this->kernel->packageUrl($package->slug, '', $lang);
        return Response::html("<!doctype html><html lang=\"{$lang}\"><head><meta charset=\"utf-8\"><title>404</title><link rel=\"stylesheet\" href=\"" . $this->kernel->assetUrl($package, 'assets/css/site.css') . "\"></head><body><main class=\"shell\"><section class=\"panel\"><h1>DISPATCH erreur 404</h1><p>Path: {$path}</p><p>Route: {$route}</p><p><a href=\"{$home}\">Retour package</a></p></section></main></body></html>", 404);
    }
}
