<?php

declare(strict_types=1);

namespace Opus\Application;

use Opus\Controller\ControllerDispatcher;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Renderer\HtmlRenderer;
use Opus\Routing\Router;
use Opus\Security\SecureDispatchGate;
use Opus\Security\SiteSecurityPolicyLoader;
use Opus\Site\SiteResolver;
use Opus\Template\ScoreTemplateRenderer;

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
 *   The dispatcher belongs to `Opus\Controller`, matching the original Opus
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
 *   P116B2 uses the native ScoreTemplate renderer. No Twig or Symfony runtime
 *   dependency is allowed in the application pipeline.
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

        $templateRenderer = new ScoreTemplateRenderer($this->paths->templatesRoot);
        $htmlRenderer = new HtmlRenderer($templateRenderer);

        return (new ControllerDispatcher($this->paths, $templateRenderer, $htmlRenderer))->dispatch($request, $match);
    }
}
