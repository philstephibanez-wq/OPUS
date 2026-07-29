<?php
declare(strict_types=1);

namespace Opus\Http;

/**
 * Builds canonical OPUS URLs without mixing path segments and query options.
 */
final class UrlBuilder implements UrlBuilderInterface
{
    public function __construct(private readonly string $basePath = '')
    {
        if (str_contains($basePath, '?') || str_contains($basePath, '#')) {
            throw new \InvalidArgumentException('OPUS_URL_BASE_PATH_INVALID');
        }
    }

    /** @param list<string> $segments @param array<string,scalar|null> $query */
    public function build(array $segments, array $query = []): string
    {
        $encoded = [];
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..'
                || str_contains($segment, '/') || str_contains($segment, '\\')
                || preg_match('/^profiler=/', $segment) === 1) {
                throw new \InvalidArgumentException(
                    'OPUS_URL_PATH_SEGMENT_INVALID'
                );
            }
            $encoded[] = rawurlencode($segment);
        }

        $path = rtrim($this->basePath, '/')
            . ($encoded === [] ? '/' : '/' . implode('/', $encoded));

        return $this->withQuery($path, $query);
    }

    /** @param array<string,scalar|null> $query */
    public function withQuery(string $url, array $query): string
    {
        if (str_contains($url, '#')) {
            throw new \InvalidArgumentException('OPUS_URL_FRAGMENT_FORBIDDEN');
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            throw new \InvalidArgumentException('OPUS_URL_PATH_INVALID');
        }
        ksort($query);
        $normalized = [];
        foreach ($query as $name => $value) {
            if (preg_match('/^[a-z][a-z0-9_.-]*$/D', $name) !== 1) {
                throw new \InvalidArgumentException(
                    'OPUS_URL_QUERY_NAME_INVALID'
                );
            }
            if ($value !== null) {
                $normalized[$name] = $value;
            }
        }
        $encoded = http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
        return $encoded === '' ? $path : $path . '?' . $encoded;
    }
}
