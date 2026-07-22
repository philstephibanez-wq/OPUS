<?php
declare(strict_types=1);

namespace Opus\Api;

use Opus\Http\Request;

/**
 * Data-driven registry for OPUS REST API routes.
 */
final class ApiRouteRegistry implements ApiRouteRegistryInterface
{
    /** @var list<ApiRoute> */
    private array $routes;

    /** @param list<ApiRoute> $routes */
    private function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_API_ROUTE_REGISTRY_MISSING: ' . $path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OPUS_API_ROUTE_REGISTRY_JSON_INVALID: ' . $path);
        }
        if (($decoded['contract'] ?? '') !== 'OPUS_API_ROUTE_REGISTRY_V1') {
            throw new \RuntimeException('OPUS_API_ROUTE_REGISTRY_CONTRACT_INVALID: ' . $path);
        }

        $routes = [];
        foreach ((array) ($decoded['routes'] ?? []) as $entry) {
            if (!is_array($entry)) {
                throw new \RuntimeException('OPUS_API_ROUTE_ENTRY_INVALID: ' . $path);
            }
            $routes[] = new ApiRoute($entry);
        }

        return new self($routes);
    }

    /** @param list<string> $segments */
    public function match(Request $request, array $segments): ?ApiRoute
    {
        $method = strtoupper($request->method);
        $path = trim(implode('/', $segments), '/');

        foreach ($this->routes as $route) {
            if ($route->method === $method && $route->path === $path) {
                return $route;
            }
        }

        return null;
    }

    /** @return list<array<string,mixed>> */
    public function export(): array
    {
        return array_map(static fn (ApiRoute $route): array => $route->meta, $this->routes);
    }
}
