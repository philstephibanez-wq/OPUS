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
 * Returns the resolved OPUS identity context.
 */
final class MeEndpoint implements ApiEndpointInterface, MeEndpointInterface
{
    public function handle(ApiRoute $route, ApplicationDefinition $application, Request $request, IdentityContextInterface $identity, array $context = []): Response
    {
        return Response::json([
            'ok' => true,
            'contract' => 'OPUS_API_RESPONSE_V1',
            'route_id' => $route->id,
            'application' => $application->slug,
            'identity' => $identity->toArray(),
        ]);
    }
}
