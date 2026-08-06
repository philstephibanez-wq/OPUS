<?php
declare(strict_types=1);

namespace Opus\Api\Rest;

use Opus\File\Json;
use Opus\File\StructuredFileLoader;

/**
 * Canonical REST resource catalog shared by OPUS clients and servers.
 *
 * The catalog is deployment-safe: peers exchange only a deterministic
 * fingerprint, never filesystem paths or configuration contents.
 */
final class RestResourceCatalog implements RestResourceCatalogInterface
{
    public const CONTRACT = 'OPUS_REST_RESOURCE_CATALOG_V1';

    /** @var list<string> */
    private const METHODS = ['DELETE', 'GET', 'PATCH', 'POST', 'PUT'];

    /** @var list<array<string,mixed>> */
    private readonly array $resources;

    private readonly string $base;
    private readonly string $catalogFingerprint;

    /**
     * @param list<array<string,mixed>> $resources
     */
    private function __construct(array $resources, string $basePath)
    {
        $this->base = self::normalizeBasePath($basePath);
        $this->resources = self::normalizeResources($resources, $this->base);
        $this->catalogFingerprint = hash(
            'sha256',
            Json::instance()->encode([
                'contract' => self::CONTRACT,
                'base_path' => $this->base,
                'resources' => self::fingerprintResources($this->resources),
            ], false)
        );
    }

    public static function fromArray(
        array $resources,
        string $basePath = '/api/v1'
    ): self {
        if ($resources === []
            || array_filter($resources, 'is_array') !== $resources) {
            throw new \RuntimeException('OPUS_REST_API_RESOURCES_EMPTY');
        }

        /** @var list<array<string,mixed>> $resources */
        return new self(array_values($resources), $basePath);
    }

    public static function fromFile(string $catalogFile): self
    {
        $catalog = StructuredFileLoader::instance()->read($catalogFile);
        if (($catalog['contract'] ?? null) !== self::CONTRACT) {
            throw new \RuntimeException(
                'OPUS_REST_RESOURCE_CATALOG_CONTRACT_INVALID'
            );
        }
        $resources = $catalog['resources'] ?? null;
        if (!is_array($resources)) {
            throw new \RuntimeException('OPUS_REST_API_RESOURCES_EMPTY');
        }
        return self::fromArray(
            $resources,
            (string) ($catalog['base_path'] ?? '')
        );
    }

    public function basePath(): string
    {
        return $this->base;
    }

    public function fingerprint(): string
    {
        return $this->catalogFingerprint;
    }

    public function routes(): array
    {
        return $this->resources;
    }

    public function resolve(string $method, string $path): array
    {
        $method = self::normalizeMethod($method);
        $path = self::normalizeRequestPath($path, $this->base);
        $allowed = [];

        foreach ($this->resources as $route) {
            $parameters = self::match(
                (string) $route['path'],
                $path
            );
            if ($parameters === null) {
                continue;
            }
            $routeMethod = (string) $route['method'];
            $allowed[] = $routeMethod;
            if ($routeMethod === $method) {
                return [$route, $parameters, []];
            }
        }

        $allowed = array_values(array_unique($allowed));
        sort($allowed, SORT_STRING);
        return [null, [], $allowed];
    }

    public function assertRequest(string $method, string $path): array
    {
        [$route, , $allowed] = $this->resolve($method, $path);
        if ($route !== null) {
            return $route;
        }
        if ($allowed !== []) {
            throw new \RuntimeException(
                'OPUS_REST_API_METHOD_NOT_ALLOWED'
            );
        }
        throw new \RuntimeException(
            'OPUS_REST_API_RESOURCE_NOT_DECLARED'
        );
    }

    public function successStatus(array $route): int
    {
        $status = (int) ($route['success_status'] ?? 0);
        if ($status < 200 || $status > 299) {
            throw new \RuntimeException(
                'OPUS_REST_API_SUCCESS_STATUS_INVALID'
            );
        }
        return $status;
    }

    public function location(array $route, array $parameters): string
    {
        $location = trim((string) ($route['location'] ?? ''));
        if ($location === '') {
            return '';
        }
        foreach ($parameters as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                continue;
            }
            $location = str_replace(
                '{' . $name . '}',
                rawurlencode((string) $value),
                $location
            );
        }
        if (str_contains($location, '{')
            || str_contains($location, '}')) {
            throw new \RuntimeException(
                'OPUS_REST_API_LOCATION_PARAMETER_MISSING'
            );
        }
        return self::normalizeRequestPath($location, $this->base);
    }

    public function assertPeerFingerprint(string $fingerprint): void
    {
        $fingerprint = strtolower(trim($fingerprint));
        if (preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1
            || !hash_equals($this->catalogFingerprint, $fingerprint)) {
            throw new \RuntimeException(
                'OPUS_REST_API_CATALOG_MISMATCH'
            );
        }
    }

    private static function normalizeBasePath(string $basePath): string
    {
        $basePath = '/' . trim($basePath, '/');
        if (preg_match(
            '~^/api/v[1-9][0-9]*$~D',
            $basePath
        ) !== 1) {
            throw new \RuntimeException(
                'OPUS_REST_API_BASE_PATH_INVALID'
            );
        }
        return $basePath;
    }

    /**
     * @param list<array<string,mixed>> $resources
     * @return list<array<string,mixed>>
     */
    private static function normalizeResources(
        array $resources,
        string $basePath
    ): array {
        $normalized = [];
        $routeKeys = [];
        $operations = [];

        foreach ($resources as $route) {
            $method = self::normalizeMethod(
                (string) ($route['method'] ?? '')
            );
            $path = self::normalizeTemplate(
                (string) ($route['path'] ?? ''),
                $basePath
            );
            $operation = trim((string) ($route['operation'] ?? ''));
            if (preg_match(
                '/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D',
                $operation
            ) !== 1) {
                throw new \RuntimeException(
                    'OPUS_REST_API_RESOURCE_DEFINITION_INVALID'
                );
            }
            $status = (int) ($route['success_status'] ?? 0);
            if ($status < 200 || $status > 299) {
                throw new \RuntimeException(
                    'OPUS_REST_API_SUCCESS_STATUS_INVALID'
                );
            }
            $routeKey = $method . ' ' . $path;
            if (isset($routeKeys[$routeKey])) {
                throw new \RuntimeException(
                    'OPUS_REST_API_RESOURCE_DUPLICATE'
                );
            }
            if (isset($operations[$operation])) {
                throw new \RuntimeException(
                    'OPUS_REST_API_OPERATION_DUPLICATE'
                );
            }
            $routeKeys[$routeKey] = true;
            $operations[$operation] = true;

            $normalizedRoute = [
                'method' => $method,
                'path' => $path,
                'operation' => $operation,
                'success_status' => $status,
            ];
            if (array_key_exists('parameters', $route)) {
                if (!is_array($route['parameters'])) {
                    throw new \RuntimeException(
                        'OPUS_REST_API_RESOURCE_PARAMETERS_INVALID'
                    );
                }
                $normalizedRoute['parameters'] = self::canonicalValue(
                    $route['parameters']
                );
            }
            if (array_key_exists('location', $route)) {
                $location = trim((string) $route['location']);
                self::normalizeTemplate($location, $basePath, true);
                $normalizedRoute['location'] = $location;
            }
            $normalized[] = $normalizedRoute;
        }

        return $normalized;
    }

    private static function normalizeMethod(string $method): string
    {
        $method = strtoupper(trim($method));
        if (!in_array($method, self::METHODS, true)) {
            throw new \RuntimeException('OPUS_REST_API_METHOD_INVALID');
        }
        return $method;
    }

    private static function normalizeTemplate(
        string $template,
        string $basePath,
        bool $location = false
    ): string {
        $template = '/' . trim($template, '/');
        if (!str_starts_with($template, $basePath . '/')
            || strlen($template) > 768
            || str_contains($template, "\0")
            || str_contains($template, '\\')
            || str_contains($template, '?')
            || str_contains($template, '#')
            || str_contains($template, '//')) {
            throw new \RuntimeException(
                'OPUS_REST_API_RESOURCE_DEFINITION_INVALID'
            );
        }

        $names = [];
        $segments = explode('/', trim($template, '/'));
        foreach ($segments as $index => $segment) {
            if (preg_match(
                '/^\{(\*?)([a-z][a-z0-9_]*)\}$/D',
                $segment,
                $match
            ) === 1) {
                $name = $match[2];
                if (isset($names[$name])
                    || ($match[1] === '*'
                        && $index !== array_key_last($segments))) {
                    throw new \RuntimeException(
                        'OPUS_REST_API_RESOURCE_DEFINITION_INVALID'
                    );
                }
                $names[$name] = true;
                continue;
            }
            if ($location && str_contains($segment, '{')) {
                if (preg_match(
                    '/^[A-Za-z0-9._~-]*\{[a-z][a-z0-9_]*\}'
                    . '[A-Za-z0-9._~-]*$/D',
                    $segment
                ) === 1) {
                    continue;
                }
            }
            if (preg_match('/^[A-Za-z0-9._~-]+$/D', $segment) !== 1) {
                throw new \RuntimeException(
                    'OPUS_REST_API_RESOURCE_DEFINITION_INVALID'
                );
            }
        }
        return $template;
    }

    private static function normalizeRequestPath(
        string $path,
        string $basePath
    ): string {
        $path = '/' . ltrim(trim($path), '/');
        if (!str_starts_with($path, $basePath . '/')
            || strlen($path) > 2048
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_contains($path, '?')
            || str_contains($path, '#')
            || str_contains($path, '//')
            || preg_match('/%(?![A-Fa-f0-9]{2})/', $path) === 1) {
            throw new \RuntimeException(
                'OPUS_REST_API_RESOURCE_INVALID'
            );
        }
        foreach (explode('/', trim($path, '/')) as $segment) {
            $decoded = rawurldecode($segment);
            if ($decoded === ''
                || $decoded === '.'
                || $decoded === '..'
                || str_contains($decoded, "\0")
                || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) {
                throw new \RuntimeException(
                    'OPUS_REST_API_RESOURCE_INVALID'
                );
            }
        }
        return $path;
    }

    /** @return array<string,string>|null */
    private static function match(string $template, string $path): ?array
    {
        $names = [];
        $wildcards = [];
        $pattern = '';
        foreach (explode('/', trim($template, '/')) as $segment) {
            $pattern .= '/';
            if (preg_match(
                '/^\{(\*?)([a-z][a-z0-9_]*)\}$/D',
                $segment,
                $part
            ) === 1) {
                $names[] = $part[2];
                $wildcards[] = $part[1] === '*';
                $pattern .= $part[1] === '*' ? '(.+)' : '([^/]+)';
                continue;
            }
            $pattern .= preg_quote($segment, '~');
        }
        if (preg_match('~^' . $pattern . '$~D', $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($names as $index => $name) {
            $value = rawurldecode((string) ($matches[$index + 1] ?? ''));
            if ($value === ''
                || str_contains($value, "\0")
                || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
                || (!$wildcards[$index] && str_contains($value, '/'))) {
                throw new \RuntimeException(
                    'OPUS_REST_API_RESOURCE_PARAMETER_INVALID'
                );
            }
            $parameters[$name] = $value;
        }
        return $parameters;
    }

    /**
     * @param list<array<string,mixed>> $resources
     * @return list<array<string,mixed>>
     */
    private static function fingerprintResources(array $resources): array
    {
        usort(
            $resources,
            static fn (array $left, array $right): int => strcmp(
                (string) $left['method'] . ' ' . (string) $left['path'],
                (string) $right['method'] . ' ' . (string) $right['path']
            )
        );
        return array_map(
            static fn (mixed $value): mixed => self::canonicalValue($value),
            $resources
        );
    }

    /** @param array<mixed> $value */
    private static function isList(array $value): bool
    {
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }

    private static function canonicalValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            if (is_scalar($value) || $value === null) {
                return $value;
            }
            throw new \RuntimeException(
                'OPUS_REST_RESOURCE_CATALOG_VALUE_INVALID'
            );
        }
        if (self::isList($value)) {
            return array_map(
                static fn (mixed $child): mixed =>
                    self::canonicalValue($child),
                $value
            );
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $child) {
            if (!is_string($key)) {
                throw new \RuntimeException(
                    'OPUS_REST_RESOURCE_CATALOG_VALUE_INVALID'
                );
            }
            $value[$key] = self::canonicalValue($child);
        }
        return $value;
    }
}
