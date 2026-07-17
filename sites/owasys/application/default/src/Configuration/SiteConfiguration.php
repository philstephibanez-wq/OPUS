<?php
declare(strict_types=1);

namespace Owasys\Application\Configuration;

use RuntimeException;

final class SiteConfiguration
{
    /** @var array<string,mixed> */
    private array $site;

    /** @var list<array<string,mixed>> */
    private array $routes;

    private string $siteRoot;

    private function __construct(string $siteRoot, array $site, array $routes)
    {
        $this->siteRoot = $siteRoot;
        $this->site = $site;
        $this->routes = $routes;
    }

    public static function load(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if ($siteRoot === '' || !is_dir($siteRoot)) {
            throw new RuntimeException('OWASYS_SITE_ROOT_INVALID');
        }

        $site = self::readJson($siteRoot . '/config/site.json', 'OWASYS_SITE_CONFIG_INVALID');
        $routesDocument = self::readJson($siteRoot . '/config/routes.json', 'OWASYS_ROUTES_CONFIG_INVALID');
        $routes = $routesDocument['routes'] ?? null;
        if (!is_array($routes)) {
            throw new RuntimeException('OWASYS_ROUTES_CONFIG_INVALID');
        }
        if (($site['states_root'] ?? null) !== 'application/states' || ($site['dispatch_model'] ?? null) !== 'state-first') {
            throw new RuntimeException('OWASYS_STATE_ROOT_INVALID');
        }

        return new self($siteRoot, $site, array_values(array_filter($routes, 'is_array')));
    }

    public function siteRoot(): string
    {
        return $this->siteRoot;
    }

    /** @return array<string,mixed> */
    public function site(): array
    {
        return $this->site;
    }

    /** @return list<array<string,mixed>> */
    public function routes(): array
    {
        return $this->routes;
    }

    /** @return array<string,mixed> */
    public function routeByPath(string $path): array
    {
        foreach ($this->routes as $route) {
            if (($route['path'] ?? null) === $path) {
                return $route;
            }
        }

        throw new RuntimeException('OWASYS_ROUTE_NOT_FOUND:' . $path);
    }

    /** @return array<string,mixed> */
    public function auth(): array
    {
        return is_array($this->site['auth'] ?? null) ? $this->site['auth'] : [];
    }

    /** @return list<string> */
    public function locales(): array
    {
        return array_values(array_filter((array) ($this->site['locales'] ?? ['fr']), 'is_string'));
    }

    public function defaultLocale(): string
    {
        $locales = $this->locales();
        $candidate = (string) ($this->site['default_locale'] ?? 'fr');
        return in_array($candidate, $locales, true) ? $candidate : 'fr';
    }

    private static function readJson(string $file, string $error): array
    {
        if (!is_file($file)) {
            throw new RuntimeException($error);
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            throw new RuntimeException($error);
        }
        return $decoded;
    }
}
