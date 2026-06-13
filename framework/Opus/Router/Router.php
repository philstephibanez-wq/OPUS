<?php

declare(strict_types=1);

namespace Opus\Router;

/*
 * OPUS_REFBOOK:
 *   domain: ROUTER
 *   role: Class Router belongs to the ROUTER Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the ROUTER domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - router-overview
 *   diagrams:
 *     - router-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED ROUTER
 *
 * Role:
 *   Preserve the Opus ROUTER domain.
 *
 * Contract:
 *   No route fallback. Unmatched path fails clearly.
 *
 * Since:
 *   P112D4D_SAFE
 *
 * Deepened:
 *   P112D4F
 */
final class Router
{
    /** @var array<string,Route> */
    private array $routesByPath = [];

    /** @var array<string,Route> */
    private array $routesByName = [];

    /**
     * @param Route[] $routes
     */
    public function __construct(array $routes = [])
    {
        foreach ($routes as $route) {
            $this->add($route);
        }
    }

    public function add(Route $route): void
    {
        if (array_key_exists($route->path, $this->routesByPath)) {
            throw new \RuntimeException('OPUS_ROUTE_PATH_DUPLICATE: ' . $route->path);
        }

        if (array_key_exists($route->name, $this->routesByName)) {
            throw new \RuntimeException('OPUS_ROUTE_NAME_DUPLICATE: ' . $route->name);
        }

        $this->routesByPath[$route->path] = $route;
        $this->routesByName[$route->name] = $route;
    }

    public function match(string $path): Route
    {
        if (!array_key_exists($path, $this->routesByPath)) {
            throw new \RuntimeException('OPUS_ROUTE_NOT_FOUND: ' . $path);
        }

        return $this->routesByPath[$path];
    }

    public function byName(string $name): Route
    {
        if (!array_key_exists($name, $this->routesByName)) {
            throw new \RuntimeException('OPUS_ROUTE_NAME_NOT_FOUND: ' . $name);
        }

        return $this->routesByName[$name];
    }

    public function hasPath(string $path): bool
    {
        return array_key_exists($path, $this->routesByPath);
    }

    public function hasName(string $name): bool
    {
        return array_key_exists($name, $this->routesByName);
    }

    /**
     * @return Route[]
     */
    public function all(): array
    {
        return array_values($this->routesByName);
    }
}
