<?php

declare(strict_types=1);

namespace Opus\Http;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent the minimal public HTTP request data required by the OPUS public
 *   route MVC smoke pipeline.
 *
 * Responsibility:
 *   Carry only neutral request facts. It does not resolve routes, authorize
 *   actors, execute controllers, render views or expose diagnostics.
 *
 * Contract:
 *   Invalid request shape is explicit and remains an internal diagnostic. Public
 *   renderers must never expose the technical validation reason to public users.
 */
final class PublicRequest
{
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $site = 'default'
    ) {
        if ($this->method === '') {
            throw new InvalidArgumentException('OPUS_PUBLIC_REQUEST_METHOD_EMPTY');
        }

        if ($this->path === '' || $this->path[0] !== '/') {
            throw new InvalidArgumentException('OPUS_PUBLIC_REQUEST_PATH_INVALID');
        }

        if ($this->site === '') {
            throw new InvalidArgumentException('OPUS_PUBLIC_REQUEST_SITE_EMPTY');
        }
    }

    public static function get(string $path, string $site = 'default'): self
    {
        return new self('GET', $path, $site);
    }

    public function method(): string
    {
        return strtoupper($this->method);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function site(): string
    {
        return $this->site;
    }

    public function routeKey(): string
    {
        return $this->method() . ' ' . $this->path;
    }
}
