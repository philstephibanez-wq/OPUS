<?php
declare(strict_types=1);

namespace Opus\Api;

use Opus\Application\ApplicationDefinition;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * Contract implemented by every OPUS REST endpoint.
 *
 * Endpoints receive an already resolved route, an already authenticated identity context,
 * and framework-level services through an explicit context array. They must not read
 * global configuration directly.
 */
interface ApiEndpointInterface
{
    /**
     * @param array<string,mixed> $context
     */
    public function handle(ApiRoute $route, ApplicationDefinition $application, Request $request, IdentityContextInterface $identity, array $context = []): Response;
}
