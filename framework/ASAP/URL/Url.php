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
 *   Generate local application URLs and expose legacy URL getters/setters.
 *
 * Contract:
 *   URL generation only. No route matching and no redirect side effects.
 *
 * Since:
 *   P112D4E
 *
 * Deepened:
 *   P112D4F
 *
 * Legacy compatibility:
 *   P112O restores __toString() and legacy URL accessors.
 */
final class Url
{
    private string $basePath = '';
    private string $protocol = '';
    private string $host = '';
    private string $path = '';
    /** @var array<string,string|int|float|bool> */
    private array $arguments = [];
    private string $anchor = '';

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath;

        if ($basePath !== '' && preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $basePath) === 1) {
            $parts = parse_url($basePath);

            if (!is_array($parts)) {
                throw new \InvalidArgumentException('ASAP_URL_PARSE_FAILED');
            }

            $this->protocol = (string) ($parts['scheme'] ?? '');
            $this->host = (string) ($parts['host'] ?? '');
            $this->path = (string) ($parts['path'] ?? '');
            $this->anchor = (string) ($parts['fragment'] ?? '');

            if (isset($parts['query'])) {
                parse_str((string) $parts['query'], $query);
                /** @var array<string,string|int|float|bool> $query */
                $this->arguments = $query;
            }
        }
    }

    /** @param array<string,string|int|float|bool> $query */
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

    /** @param array<string,string|int|float|bool> $query */
    public function asset(string $path, array $query = []): string
    {
        return $this->to('/assets' . $this->normalizePath($path), $query);
    }

    /** @param array<string,string|int|float|bool> $query */
    public function route(string $slug, array $query = []): string
    {
        if (trim($slug) === '') {
            throw new \InvalidArgumentException('ASAP_URL_ROUTE_SLUG_EMPTY');
        }

        return $this->to('/' . ltrim($slug, '/'), $query);
    }

    public function __toString(): string
    {
        $url = '';

        if ($this->protocol !== '') {
            $url .= $this->protocol . '://';
        }

        $url .= $this->host;
        $url .= $this->path;

        if ($this->arguments !== []) {
            $url .= '?' . http_build_query($this->arguments, '', '&', PHP_QUERY_RFC3986);
        }

        if ($this->anchor !== '') {
            $url .= '#' . rawurlencode($this->anchor);
        }

        return $url;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function setProtocol(string $protocol): self
    {
        if ($protocol !== '' && preg_match('/^[a-z][a-z0-9+.-]*$/i', $protocol) !== 1) {
            throw new \InvalidArgumentException('ASAP_URL_PROTOCOL_INVALID');
        }

        $this->protocol = $protocol;

        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        if ($path !== '' && $path[0] !== '/') {
            throw new \InvalidArgumentException('ASAP_URL_LEGACY_PATH_INVALID');
        }

        $this->path = $path;

        return $this;
    }

    /** @return array<string,string|int|float|bool> */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /** @param array<string,string|int|float|bool> $arguments */
    public function setArguments(array $arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function getAnchor(): string
    {
        return $this->anchor;
    }

    public function setAnchor(string $anchor): self
    {
        $this->anchor = $anchor;

        return $this;
    }

    private function normalizePath(string $path): string
    {
        if (trim($path) === '') {
            throw new \InvalidArgumentException('ASAP_URL_PATH_EMPTY');
        }

        return '/' . ltrim($path, '/');
    }
}
