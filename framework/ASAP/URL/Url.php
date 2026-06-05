<?php

declare(strict_types=1);

namespace ASAP\Url;

/**
 * PUBLIC LEGACY-COLLISION RECONCILIATION
 *
 * Role:
 *   Preserve the ASAP URL concept inside the canonical Windows-safe
 *   `ASAP\Url` namespace/directory.
 *
 * Responsibility:
 *   Generate local application URLs.
 *
 * Contract:
 *   URL generation only. No route matching and no redirect side effects.
 *
 * Since:
 *   P112D4E
 *
 * Deepened:
 *   P112D4F
 */
final class Url
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    /**
     * @param array<string,string|int|float|bool> $query
     */
    public function to(string $path, array $query = []): string
    {
        if ($path === '' || $path[0] !== '/') {
            throw new \InvalidArgumentException('ASAP_URL_PATH_INVALID');
        }

        $url = rtrim($this->basePath, '/') . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    /**
     * @param array<string,string|int|float|bool> $query
     */
    public function asset(string $path, array $query = []): string
    {
        return $this->to('/assets' . $this->normalizePath($path), $query);
    }

    /**
     * @param array<string,string|int|float|bool> $query
     */
    public function route(string $slug, array $query = []): string
    {
        if (trim($slug) === '') {
            throw new \InvalidArgumentException('ASAP_URL_ROUTE_SLUG_EMPTY');
        }

        return $this->to('/' . ltrim($slug, '/'), $query);
    }

    private function normalizePath(string $path): string
    {
        if (trim($path) === '') {
            throw new \InvalidArgumentException('ASAP_URL_PATH_EMPTY');
        }

        return '/' . ltrim($path, '/');
    }
}
