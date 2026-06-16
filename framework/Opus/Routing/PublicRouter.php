<?php

declare(strict_types=1);

namespace Opus\Routing;

use Opus\Http\PublicRequest;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Resolve a public request against declared public routes.
 *
 * Responsibility:
 *   Return a route declaration or null. It does not authorize, execute,
 *   transform, render or expose error details.
 *
 * Contract:
 *   Unknown route resolution is not a public diagnostic. It is consumed by the
 *   control plane, which may enter a blocked state internally.
 */
final class PublicRouter
{
    /** @var PublicRoute[] */
    private array $routes = [];

    public function add(PublicRoute $route): void
    {
        $this->routes[] = $route;
    }

    public function resolve(PublicRequest $request): ?PublicRoute
    {
        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $route;
            }
        }

        return null;
    }
}
