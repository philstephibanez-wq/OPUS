<?php
declare(strict_types=1);
namespace ASAP\ROUTER;
final class Router
{
    private array $routesByPath = [];
    public function __construct(array $routes = []) { foreach ($routes as $route) { $this->add($route); } }
    public function add(Route $route): void { $this->routesByPath[$route->path] = $route; }
    public function match(string $path): Route { if (!array_key_exists($path, $this->routesByPath)) { throw new \RuntimeException('ASAP_ROUTE_NOT_FOUND: ' . $path); } return $this->routesByPath[$path]; }
}
