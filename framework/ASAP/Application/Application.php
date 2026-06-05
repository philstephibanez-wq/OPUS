<?php

declare(strict_types=1);

namespace ASAP\Application;

use ASAP\Controller\ControllerDispatcher;
use ASAP\Http\Request;
use ASAP\Http\Response;
use ASAP\Renderer\HtmlRenderer;
use ASAP\Routing\Router;
use ASAP\Security\AclGuard;
use ASAP\Security\FsmGuard;
use ASAP\Security\SiteSecurityPolicyLoader;
use ASAP\Site\SiteResolver;
use ASAP\Template\TwigTemplateRenderer;

/**
 * PUBLIC APPLICATION KERNEL
 *
 * Role:
 *   Orchestrate the minimal ASAP PHP 8 request pipeline.
 *
 * Responsibility:
 *   Site resolution, FSM guard, ACL guard, route matching, controller dispatch
 *   and response return.
 *
 * Contract:
 *   The Application orchestrates only. It does not read content, render templates
 *   directly, decide ACL rules itself, or silently compensate missing configuration.
 *
 * Pipeline:
 *   REQUEST -> SiteResolver -> FSM Guard -> ACL Guard -> Router -> Dispatcher
 *   -> Controller -> Renderer -> RESPONSE.
 *
 * Since:
 *   P112D4B
 */
final class Application
{
    public function __construct(private readonly ApplicationPaths $paths)
    {
    }

    /**
     * PUBLIC ENTRY POINT
     *
     * @param Request $request Normalized request.
     *
     * @return Response Controller response.
     */
    public function run(Request $request): Response
    {
        $site = (new SiteResolver($this->paths->sitesRoot))->resolve($request);
        $securityPolicy = (new SiteSecurityPolicyLoader())->load($site->securityFile);

        $fsmState = (new FsmGuard())->assertAllowed($request, $securityPolicy);
        (new AclGuard())->assertAllowed($request, $securityPolicy, $fsmState);

        $router = Router::fromXml($site->routesFile);
        $match = $router->match($request, $site);

        $templateRenderer = new TwigTemplateRenderer($this->paths->templatesRoot, $this->paths->cacheRoot);
        $htmlRenderer = new HtmlRenderer($templateRenderer);

        return (new ControllerDispatcher($this->paths, $templateRenderer, $htmlRenderer))->dispatch($request, $match);
    }
}
