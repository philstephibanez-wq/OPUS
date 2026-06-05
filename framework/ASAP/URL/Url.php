<?php

declare(strict_types=1);

namespace ASAP\URL;

/**
 * PUBLIC LEGACY-ALIGNED URL GENERATOR
 *
 * Role:
 *   Preserve the original ASAP `URL\Url` concept.
 *
 * Responsibility:
 *   Build local application URLs.
 *
 * Contract:
 *   URL generation only. No route matching and no redirect side effects.
 *
 * Since:
 *   P112D4C
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
}
