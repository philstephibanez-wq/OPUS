<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Http\PublicRequest;
use Opus\Log\RuntimeLogger;
use Opus\PublicSite\PublicHomeAction;
use Opus\Routing\PublicRoute;
use Opus\Routing\PublicRouter;
use Opus\Security\PublicBlockedResponseRenderer;
use Opus\Security\PublicRouteControlPlane;
use Opus\Template\SimpleScoreTemplateRenderer;
use RuntimeException;

final class PublicRouteMvcSmoke
{
    public static function run(string $projectRoot): array
    {
        $logger = new RuntimeLogger($projectRoot);
        $router = new PublicRouter();
        $router->add(PublicRoute::get('/', 'public_page', PublicHomeAction::class));

        $control = new PublicRouteControlPlane();
        $renderer = new SimpleScoreTemplateRenderer();
        $blockedRenderer = new PublicBlockedResponseRenderer();

        $normalRequest = PublicRequest::get('/', 'opus-demo');
        $normalRoute = $router->resolve($normalRequest);
        if ($normalRoute === null) {
            throw new RuntimeException('OPUS_PUBLIC_SMOKE_ROUTE_NOT_FOUND');
        }

        $normalDecision = $control->authorize($normalRequest, $normalRoute->profile());
        if (!$normalDecision->isAllowed()) {
            throw new RuntimeException('OPUS_PUBLIC_SMOKE_ROUTE_DENIED');
        }

        $controllerClass = $normalRoute->controllerClass();
        $controller = new $controllerClass();
        $normalResponse = $renderer->render($controller($normalRequest));

        $missRequest = PublicRequest::get('/missing', 'opus-demo');
        $missDecision = $control->denyUnknownRoute($missRequest);
        $blockedEvent = $missDecision->blockedStateEvent();
        if ($blockedEvent === null) {
            throw new RuntimeException('OPUS_PUBLIC_SMOKE_BLOCKED_EVENT_MISSING');
        }
        $missResponse = $blockedRenderer->render($blockedEvent);

        $logger->info('OPUS_PUBLIC_ROUTE_MVC_SMOKE', [
            'normal_status' => $normalResponse->statusCode(),
            'miss_status' => $missResponse->statusCode(),
            'blocked_event' => $blockedEvent->adminDiagnostics(),
        ]);

        return [
            'ok' => true,
            'gate' => 'P117A2_OPUS_PUBLIC_ROUTE_MVC_SMOKE',
            'normal_public_response' => $normalResponse->toArray(),
            'blocked_public_response' => $missResponse->toArray(),
            'blocked_state_event' => $blockedEvent->adminDiagnostics(),
            'admin_diagnostics' => [
                'normal' => $normalDecision->adminDiagnostics(),
                'blocked' => $missDecision->adminDiagnostics(),
            ],
        ];
    }
}
