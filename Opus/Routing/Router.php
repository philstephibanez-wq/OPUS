<?php
declare(strict_types=1);

namespace Opus\Routing;

use Opus\Api\ApiDispatcher;
use Opus\Application\ApplicationDefinition;
use Opus\Foundation\Support;
use Opus\Fsm\FsmSiteLoader;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Profiler\Profiler;
use Opus\Runtime\Kernel;
use Opus\Security\Acl;
use Opus\View\View;
use Opus\Fsm\Runtime\FsmRuntimeConfigLoader;
use RuntimeException;
use Throwable;

/**
 * Runtime router for integrated OPUS applications.
 *
 * The URL layer resolves a localized path to an FSM signal only.
 * The FSM transition is the sole authority for selecting the target state.
 */
final class Router implements RouterInterface
{
    private Kernel $kernel;
    private View $view;
    private Acl $acl;
    private Profiler $profiler;
    private FsmRuntimeConfigLoader $fsmRuntimeConfigLoader;

    public function __construct(
        Kernel $kernel,
        View $view,
        Acl $acl,
        Profiler $profiler,
        FsmRuntimeConfigLoader $fsmRuntimeConfigLoader
    ) {
        $this->kernel = $kernel;
        $this->view = $view;
        $this->acl = $acl;
        $this->profiler = $profiler;
        $this->fsmRuntimeConfigLoader = $fsmRuntimeConfigLoader;
    }

    /** @param list<string> $segments */
    public function dispatch(ApplicationDefinition $application, array $segments, Request $request): Response
    {
        $this->profiler->event('routing', 'dispatch.start', [
            'application' => $application->slug,
            'segments' => $segments,
        ]);

        if (($segments[0] ?? '') === 'api') {
            return $this->dispatchApi($application, array_slice($segments, 1), $request);
        }

        [$lang, $routeSegments] = $this->resolveLanguage($application, $segments);
        $routeKey = implode('/', $routeSegments);

        /*
         * routes.php is no longer allowed to map URL => page/state/controller.
         * It is only the localized URL projection URL => FSM signal.
         */
        $signal = $this->resolveSignal($application, $lang, $routeKey);
        if ($signal === '') {
            return $this->notFound($application, $lang, $request, $routeKey);
        }

        $sourceState = $this->currentState($application);

        try {
            $processor = FsmSiteLoader::processorForSiteRoot($application->dir);
            $transition = $processor->transition($sourceState, $signal);
        } catch (Throwable $exception) {
            $this->profiler->event('routing', 'fsm.transition.rejected', [
                'application' => $application->slug,
                'source_state' => $sourceState,
                'signal' => $signal,
                'error' => $exception->getMessage(),
            ]);

            return Response::html('<h1>409</h1><p>FSM transition rejected.</p>', 409);
        }

        $targetState = trim((string)($transition['to_state'] ?? ''));
        if ($targetState === '') {
            throw new RuntimeException(
                'OPUS_ROUTER_FSM_TARGET_STATE_MISSING: '
                . $application->slug . ':' . $sourceState . ':' . $signal
            );
        }

        $_SESSION[$this->stateSessionKey($application)] = $targetState;

        $this->profiler->event('routing', 'fsm.transition.accepted', [
            'application' => $application->slug,
            'source_state' => $sourceState,
            'signal' => $signal,
            'target_state' => $targetState,
        ]);

        /*
         * MVC dispatch is derived exclusively from the FSM target state.
         * No URL entry may select a page, controller or state directly.
         */
        $content = $application->content();
        $page = (array)($content[$lang][$targetState] ?? []);
        if (!$page) {
            throw new RuntimeException(
                "State content missing: {$application->slug}/{$lang}/{$targetState}"
            );
        }

        if (!$this->acl->canView($page)) {
            $this->profiler->event('routing', 'acl.forbidden', [
                'target_state' => $targetState,
            ]);

            return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
        }

        if ($targetState === 'fsm') {
            $page['flow'] = $this->fsmRuntimeConfigLoader->flowForDisplay('runtime_request');
            $this->profiler->event('runtime', 'fsm_runtime_config.loaded', [
                'id' => 'runtime_request',
            ]);
        }

        $this->profiler->event('template', 'view.render.start', [
            'target_state' => $targetState,
        ]);
        $html = $this->view->render($application, $lang, $targetState, $page);
        $this->profiler->event('template', 'view.render.done', [
            'target_state' => $targetState,
        ]);

        return Response::html($html);
    }

    /**
     * @param list<string> $segments
     * @return array{0:string,1:list<string>}
     */
    private function resolveLanguage(ApplicationDefinition $application, array $segments): array
    {
        $lang = $segments[0] ?? $application->defaultLang;

        if (!$application->hasLanguage($lang)) {
            return [$application->defaultLang, $segments];
        }

        return [$lang, array_values(array_slice($segments, 1))];
    }

    private function resolveSignal(
        ApplicationDefinition $application,
        string $lang,
        string $routeKey
    ): string {
        $routes = $application->routes();
        $langRoutes = (array)($routes[$lang] ?? []);
        $signal = trim((string)($langRoutes[$routeKey] ?? ''));

        $this->profiler->event('routing', 'signal.resolved', [
            'lang' => $lang,
            'route' => $routeKey,
            'signal' => $signal,
        ]);

        return $signal;
    }

    private function currentState(ApplicationDefinition $application): string
    {
        $key = $this->stateSessionKey($application);
        $candidate = trim((string)($_SESSION[$key] ?? ''));

        return $candidate !== '' ? $candidate : $application->initialState();
    }

    private function stateSessionKey(ApplicationDefinition $application): string
    {
        return 'opus_fsm_state_' . $application->slug;
    }

    /** @param list<string> $segments */
    private function dispatchApi(
        ApplicationDefinition $application,
        array $segments,
        Request $request
    ): Response {
        $dispatcher = ApiDispatcher::fromProjectRoot(
            dirname(__DIR__, 2),
            $this->profiler,
            $this->fsmRuntimeConfigLoader
        );

        return $dispatcher->dispatch($application, $segments, $request);
    }

    private function notFound(
        ApplicationDefinition $application,
        string $lang,
        Request $request,
        string $routeKey
    ): Response {
        $this->profiler->event('routing', 'route.not_found', [
            'path' => $request->path,
            'route' => $routeKey,
        ]);

        $route = Support::e($routeKey);
        $path = Support::e($request->path);
        $home = $this->kernel->applicationUrl($application->slug, '', $lang);

        return Response::html(
            "<!doctype html><html lang=\"{$lang}\"><head><meta charset=\"utf-8\">"
            . "<title>404</title><link rel=\"stylesheet\" href=\""
            . $this->kernel->assetUrl($application, 'assets/css/site.css')
            . "\"></head><body><main class=\"shell\"><section class=\"panel\">"
            . "<h1>DISPATCH erreur 404</h1><p>Path: {$path}</p>"
            . "<p>Route: {$route}</p><p><a href=\"{$home}\">Retour application</a></p>"
            . "</section></main></body></html>",
            404
        );
    }
}
