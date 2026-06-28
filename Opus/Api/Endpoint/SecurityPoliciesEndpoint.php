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
 * Exposes configured API routes and ACL policies for authorized development diagnostics.
 */
final class SecurityPoliciesEndpoint implements ApiEndpointInterface
{
    public function handle(ApiRoute $route, ApplicationDefinition $application, Request $request, IdentityContextInterface $identity, array $context = []): Response
    {
        return Response::json([
            'ok' => true,
            'contract' => 'OPUS_API_RESPONSE_V1',
            'route_id' => $route->id,
            'application' => $application->slug,
            'identity' => [
                'subject' => $identity->subject(),
                'roles' => $identity->roles(),
                'scopes' => $identity->scopes(),
            ],
            'api_routes' => $context['api_routes'] ?? [],
            'acl_policies' => $context['acl_policies'] ?? [],
        ]);
    }
}
