<?php
declare(strict_types=1);

namespace Opus\Api\Endpoint;

use Opus\Api\ApiEndpointInterface;
use Opus\Api\ApiRoute;
use Opus\Application\ApplicationDefinition;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * Generic OPUS REST status endpoint.
 */
final class StatusEndpoint implements ApiEndpointInterface, StatusEndpointInterface
{
    public function handle(ApiRoute $route, ApplicationDefinition $application, Request $request, IdentityContextInterface $identity, array $context = []): Response
    {
        return Response::json([
            'ok' => true,
            'contract' => 'OPUS_API_RESPONSE_V1',
            'route' => [
                'id' => $route->id,
                'method' => $route->method,
                'path' => $route->path,
            ],
            'application' => [
                'slug' => $application->slug,
                'name' => $application->name,
                'languages' => $application->languages,
            ],
            'request' => [
                'host' => $request->host,
                'base_path' => $request->basePath,
                'path' => $request->path,
            ],
            'identity' => [
                'subject' => $identity->subject(),
                'anonymous' => $identity->isAnonymous(),
            ],
            'time' => date(DATE_ATOM),
        ]);
    }
}
