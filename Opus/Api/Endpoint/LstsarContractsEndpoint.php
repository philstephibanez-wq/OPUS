<?php
declare(strict_types=1);

namespace Opus\Api\Endpoint;

use Opus\Api\ApiEndpointInterface;
use Opus\Api\ApiRoute;
use Opus\Application\ApplicationDefinition;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Lstsar\Config\LstsarContractRegistry;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * REST endpoint exposing the declared LSTSAR contract catalog.
 */
final class LstsarContractsEndpoint implements ApiEndpointInterface, LstsarContractsEndpointInterface
{
    public function handle(ApiRoute $route, ApplicationDefinition $application, Request $request, IdentityContextInterface $identity, array $context = []): Response
    {
        $registry = LstsarContractRegistry::fromProjectRoot((string) ($context['project_root'] ?? ''));

        return Response::json([
            'ok' => true,
            'contract' => 'OPUS_LSTSAR_API_CONTRACTS_RESPONSE_V1',
            'application' => $application->slug,
            'route_id' => $route->id,
            'identity' => $identity->toArray(),
            'lstsar' => $registry->export(),
        ]);
    }
}
