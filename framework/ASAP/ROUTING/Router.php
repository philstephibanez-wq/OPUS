<?php

declare(strict_types=1);

namespace ASAP\Routing;

use ASAP\Contract\ContractException;
use ASAP\Http\Request;
use ASAP\Site\SiteDefinition;
use SimpleXMLElement;

/**
 * PUBLIC ENGINE
 *
 * Role:
 *   Match a normalized request against explicit ASAP routes.
 *
 * Responsibility:
 *   Transform route XML definitions into RouteMatch objects.
 *
 * Contract:
 *   No implicit route fallback. Missing routes fail explicitly.
 *
 * Since:
 *   P112D1
 */
final class Router
{
    /**
     * @param RouteDefinition[] $routes
     */
    public function __construct(private readonly array $routes)
    {
        if ($this->routes === []) {
            throw ContractException::because('ASAP_ROUTE_TABLE_EMPTY');
        }
    }

    /**
     * PUBLIC FACTORY
     *
     * @param string $routesFile Route XML path.
     *
     * @return self Router.
     */
    public static function fromXml(string $routesFile): self
    {
        if (!is_file($routesFile)) {
            throw ContractException::because('ASAP_ROUTES_FILE_MISSING', $routesFile);
        }

        $xml = simplexml_load_file($routesFile);

        if (!$xml instanceof SimpleXMLElement) {
            throw ContractException::because('ASAP_ROUTES_XML_INVALID', $routesFile);
        }

        $routes = [];

        foreach ($xml->route as $routeNode) {
            $target = $routeNode->target;

            if (!$target instanceof SimpleXMLElement) {
                throw ContractException::because('ASAP_ROUTE_TARGET_MISSING', (string) ($routeNode['name'] ?? ''));
            }

            $defaults = [];

            if (isset($routeNode->defaults)) {
                foreach ($routeNode->defaults->param as $param) {
                    $paramName = trim((string) ($param['name'] ?? ''));

                    if ($paramName === '') {
                        throw ContractException::because('ASAP_ROUTE_DEFAULT_PARAM_NAME_EMPTY', (string) ($routeNode['name'] ?? ''));
                    }

                    $defaults[$paramName] = (string) $param;
                }
            }

            $routes[] = new RouteDefinition(
                (string) $routeNode['name'],
                (string) $routeNode['path'],
                (string) $target['controllerClass'],
                (string) $target['action'],
                $defaults
            );
        }

        return new self($routes);
    }

    /**
     * PUBLIC API
     *
     * @param Request $request Request.
     * @param SiteDefinition $site Resolved site.
     *
     * @return RouteMatch Match.
     */
    public function match(Request $request, SiteDefinition $site): RouteMatch
    {
        $localPath = $this->toLocalPath($request->path, $site->basePath);

        foreach ($this->routes as $route) {
            $params = $this->matchRoute($route, $localPath);

            if ($params !== null) {
                return new RouteMatch(
                    $route->name,
                    $route->controllerClass,
                    $route->action,
                    array_merge($route->defaults, $params)
                );
            }
        }

        throw ContractException::because('ASAP_ROUTE_NOT_FOUND', $localPath);
    }

    private function toLocalPath(string $path, string $basePath): string
    {
        $basePath = rtrim($basePath, '/');

        if ($path === $basePath) {
            return '/';
        }

        if (!str_starts_with($path, $basePath . '/')) {
            throw ContractException::because('ASAP_REQUEST_OUTSIDE_SITE_BASE_PATH', $path);
        }

        $local = substr($path, strlen($basePath));

        return $local === '' ? '/' : $local;
    }

    /**
     * @return array<string,string>|null
     */
    private function matchRoute(RouteDefinition $route, string $localPath): ?array
    {
        if ($route->path === $localPath) {
            return [];
        }

        if ($route->path === '/{slug}') {
            $slug = trim($localPath, '/');

            if ($slug !== '' && preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) === 1) {
                return ['slug' => $slug];
            }
        }

        return null;
    }
}
