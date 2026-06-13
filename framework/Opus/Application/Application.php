<?php

declare(strict_types=1);

namespace Opus\Application;

use ASAP\Controller\ControllerDispatcher;
use ASAP\Http\Request;
use ASAP\Http\Response;
use ASAP\Renderer\HtmlRenderer;
use ASAP\Routing\Router;
use ASAP\Security\SecureDispatchGate;
use ASAP\Security\SiteSecurityPolicyLoader;
use ASAP\Site\SiteResolver;
use ASAP\Template\TwigTemplateRenderer;

/*
 * OPUS_REFBOOK:
 *   domain: APPLICATION
 *   role: Class Application belongs to the APPLICATION Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the APPLICATION domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - application-overview
 *     - secure-dispatch-gate
 *   diagrams:
 *     - application-runtime
 *     - secure-dispatch-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC APPLICATION KERNEL
 *
 * Role:
 *   Orchestrate the Opus PHP 8 request pipeline.
 *
 * Responsibility:
 *   Site resolution, route candidate resolution, secure dispatch gate validation,
 *   controller dispatch and response return.
 *
 * Legacy alignment:
 *   The dispatcher belongs to `ASAP\Controller`, matching the original ASAP
 *   namespace/domain naming.
 *
 * Contract:
 *   The Application orchestrates only. It does not read content, render templates
 *   directly, decide ACL rules itself, or silently compensate missing configuration.
 *   No controller/action dispatch is allowed before SecureDispatchGate validates
 *   the route-aware FSM and ACL contracts.
 *
 * Since:
 *   P112D4C
 *
 * Extended:
 *   P112Q3B routes requests through SecureDispatchGate before controller dispatch.
 */
final class Application
{
    public function __construct(private readonly ApplicationPaths $paths)
    {
    }

    /**
     * PUBLIC API
     *
     * Role:
     *   Execute one HTTP request through the official secure-by-design Opus pipeline.
     *
     * @param Request $request Normalized HTTP request.
     *
     * @return Response Controller response produced only after secure gate approval.
     *
     * Side effects:
     *   Instantiates the route matcher, security policy loader, secure gate and renderer
     *   services needed for the request lifecycle.
     *
     * Contract:
     *   The route candidate must be known before authorization, and controller dispatch
     *   must remain impossible before SecureDispatchGate succeeds.
     */
    public function run(Request $request): Response
    {
        $site = (new SiteResolver($this->paths->sitesRoot))->resolve($request);
        $securityPolicy = (new SiteSecurityPolicyLoader())->load($site->securityFile);

        $router = Router::fromXml($site->routesFile);
        $match = $router->match($request, $site);

        (new SecureDispatchGate())->assertAllowed($request, $securityPolicy, $match);

        $templateRenderer = new TwigTemplateRenderer($this->paths->templatesRoot, $this->paths->cacheRoot);
        $htmlRenderer = new HtmlRenderer($templateRenderer);

        return (new ControllerDispatcher($this->paths, $templateRenderer, $htmlRenderer))->dispatch($request, $match);
    }
}
