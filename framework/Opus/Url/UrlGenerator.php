<?php

declare(strict_types=1);

namespace Opus\Url;

/*
 * OPUS_REFBOOK:
 *   domain: URL
 *   role: Class UrlGenerator belongs to the URL Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the URL domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - url-overview
 *   diagrams:
 *     - url-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Generate local application URLs.
 *
 * Responsibility:
 *   Combine base path, path and query parameters.
 *
 * Contract:
 *   URL generation only. No routing match and no HTTP redirect.
 *
 * Since:
 *   P112D4B
 */
final class UrlGenerator
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    /**
     * @param array<string,string|int|float|bool> $query
     */
    public function path(string $path, array $query = []): string
    {
        if ($path === '' || $path[0] !== '/') {
            throw UrlException::because('OPUS_URL_PATH_INVALID', $path);
        }

        $url = rtrim($this->basePath, '/') . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }
}
