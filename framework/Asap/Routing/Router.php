<?php

declare(strict_types=1);

namespace ASAP\Routing;

use ASAP\Contract\ContractException;
use ASAP\Http\Request;
use ASAP\Site\SiteDefinition;
use SimpleXMLElement;

/*
 * ASAP_REFBOOK:
 *   domain: ROUTING
 *   role: Class Router belongs to the ROUTING ASAP framework domain.
 *   contract:
 *     - keeps responsibility limited to the ROUTING domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - routing-overview
 *     - secure-dispatch-gate
 *   diagrams:
 *     - routing-runtime
 *     - secure-dispatch-runtime
 * END_ASAP_REFBOOK
 */
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
 *
 * Extended:
 *   P112Q3B hydrates explicit route-level ACL/FSM metadata used by SecureDispatchGate.
 *   P112Q3B4 enforces explicit HTTP methods before secure dispatch, so form POST
 *   contracts cannot be reached through an implicit GET fallback.
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
                $defaults,
                self::parseMethods(self::firstAttribute($routeNode, ['methods', 'method'])),
                self::firstAttribute($routeNode, ['host']),
                self::firstAttribute($routeNode, ['locale', 'lang']),
                self::firstAttribute($routeNode, ['format']) ?? 'html',
                self::readAclMetadata($routeNode),
                self::readFsmGuardMetadata($routeNode),
                self::parsePriority(self::firstAttribute($routeNode, ['priority'])),
                self::firstAttribute($routeNode, ['source']) ?? 'explicit'
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
        $requestMethod = strtoupper(trim($request->method));
        $methodMismatches = [];

        foreach ($this->routes as $route) {
            $params = $this->matchRoute($route, $localPath);

            if ($params === null) {
                continue;
            }

            $allowedMethods = $route->normalizedMethods();

            if (!in_array($requestMethod, $allowedMethods, true)) {
                $methodMismatches[] = $route->name . '[' . implode(',', $allowedMethods) . ']';
                continue;
            }

            return new RouteMatch(
                $route->name,
                $route->controllerClass,
                $route->action,
                array_merge($route->defaults, $params),
                $route->acl,
                $route->fsmGuard
            );
        }

        if ($methodMismatches !== []) {
            throw ContractException::because(
                'ASAP_ROUTE_METHOD_NOT_ALLOWED',
                $requestMethod . ' ' . $localPath . ' allowed-routes=' . implode('|', $methodMismatches)
            );
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

    /**
     * INTERNAL XML HELPER
     *
     * Role:
     *   Return the first non-empty attribute value among declared attribute names.
     *
     * @param SimpleXMLElement $node XML node.
     * @param string[] $names Accepted attribute names.
     *
     * @return string|null Trimmed value, or null when not declared.
     */
    private static function firstAttribute(SimpleXMLElement $node, array $names): ?string
    {
        $accepted = array_flip($names);

        foreach ($node->attributes() as $name => $value) {
            if (isset($accepted[$name])) {
                $candidate = trim((string) $value);

                return $candidate === '' ? null : $candidate;
            }
        }

        return null;
    }

    /**
     * INTERNAL XML HELPER
     *
     * @param string|null $rawMethods Comma/pipe/space separated method list.
     *
     * @return string[] Explicit normalized HTTP methods.
     */
    private static function parseMethods(?string $rawMethods): array
    {
        if ($rawMethods === null) {
            return ['GET'];
        }

        $methods = preg_split('/[|,\s]+/', strtoupper($rawMethods)) ?: [];
        $methods = array_values(array_unique(array_filter(array_map('trim', $methods))));
        sort($methods);

        if ($methods === []) {
            throw ContractException::because('ASAP_ROUTE_METHODS_EMPTY');
        }

        return $methods;
    }

    /**
     * INTERNAL XML HELPER
     *
     * @param string|null $rawPriority Route priority value.
     *
     * @return int Normalized priority.
     */
    private static function parsePriority(?string $rawPriority): int
    {
        if ($rawPriority === null) {
            return 0;
        }

        if (preg_match('/^-?\d+$/', $rawPriority) !== 1) {
            throw ContractException::because('ASAP_ROUTE_PRIORITY_INVALID', $rawPriority);
        }

        return (int) $rawPriority;
    }

    /**
     * INTERNAL XML HELPER
     *
     * Role:
     *   Read route ACL metadata from the official route XML contract.
     *
     * Supported declarations:
     *   - `<route acl="resource:privilege">`
     *   - `<route acl="role:resource:privilege">`
     *   - `<route><security acl="resource:privilege" /></route>`
     *   - `<route><acl resource="page" privilege="read" /></route>`
     *
     * @return string|null ACL metadata, or null when the site global policy remains authoritative.
     */
    private static function readAclMetadata(SimpleXMLElement $routeNode): ?string
    {
        $direct = self::firstAttribute($routeNode, ['acl', 'access']);

        if ($direct !== null) {
            return $direct;
        }

        if (isset($routeNode->security)) {
            $securityValue = self::firstAttribute($routeNode->security, ['acl', 'access']);

            if ($securityValue !== null) {
                return $securityValue;
            }
        }

        if (!isset($routeNode->acl)) {
            return null;
        }

        $aclNode = $routeNode->acl;
        $inline = trim((string) $aclNode);

        if ($inline !== '') {
            return $inline;
        }

        $resource = self::firstAttribute($aclNode, ['resource']);
        $privilege = self::firstAttribute($aclNode, ['privilege']);
        $role = self::firstAttribute($aclNode, ['role']);

        if ($resource === null || $privilege === null) {
            throw ContractException::because('ASAP_ROUTE_ACL_METADATA_INVALID', (string) ($routeNode['name'] ?? ''));
        }

        return $role === null ? $resource . ':' . $privilege : $role . ':' . $resource . ':' . $privilege;
    }

    /**
     * INTERNAL XML HELPER
     *
     * Role:
     *   Read route FSM signal metadata from the official route XML contract.
     *
     * Supported declarations:
     *   - `<route fsmGuard="ROUTE_HOME">`
     *   - `<route fsm_guard="ROUTE_HOME">`
     *   - `<route><security fsmGuard="ROUTE_HOME" /></route>`
     *   - `<route><fsm signal="ROUTE_HOME" /></route>`
     *
     * @return string|null Route FSM signal, or null when the site global policy signal remains authoritative.
     */
    private static function readFsmGuardMetadata(SimpleXMLElement $routeNode): ?string
    {
        $direct = self::firstAttribute($routeNode, ['fsmGuard', 'fsm_guard', 'fsm']);

        if ($direct !== null) {
            return $direct;
        }

        if (isset($routeNode->security)) {
            $securityValue = self::firstAttribute($routeNode->security, ['fsmGuard', 'fsm_guard', 'fsm', 'signal']);

            if ($securityValue !== null) {
                return $securityValue;
            }
        }

        if (!isset($routeNode->fsm)) {
            return null;
        }

        $signal = self::firstAttribute($routeNode->fsm, ['signal', 'guard']);

        if ($signal === null) {
            $inline = trim((string) $routeNode->fsm);
            $signal = $inline === '' ? null : $inline;
        }

        if ($signal === null) {
            throw ContractException::because('ASAP_ROUTE_FSM_METADATA_INVALID', (string) ($routeNode['name'] ?? ''));
        }

        return $signal;
    }
}
