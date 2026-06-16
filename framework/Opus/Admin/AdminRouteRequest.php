<?php

declare(strict_types=1);

namespace Opus\Admin;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent the minimal administrator route request facts required by the
 *   native OPUS admin dashboard route smoke.
 *
 * Responsibility:
 *   Carry admin request intent, identity context and declared capabilities
 *   without executing dashboards, authorizing access or exposing diagnostics.
 *
 * Contract:
 *   This object is internal to OPUS admin routing. Invalid shape is an internal
 *   diagnostic. Public responses must never expose its validation errors.
 */
final class AdminRouteRequest
{
    /**
     * @param list<string> $roles
     * @param list<string> $scopes
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $site,
        private readonly string $identityContext,
        private readonly array $roles,
        private readonly array $scopes
    ) {
        if ($this->method === '') {
            throw new InvalidArgumentException('OPUS_ADMIN_ROUTE_REQUEST_METHOD_EMPTY');
        }

        if ($this->path === '' || $this->path[0] !== '/') {
            throw new InvalidArgumentException('OPUS_ADMIN_ROUTE_REQUEST_PATH_INVALID');
        }

        if ($this->site === '') {
            throw new InvalidArgumentException('OPUS_ADMIN_ROUTE_REQUEST_SITE_EMPTY');
        }

        if ($this->identityContext === '') {
            throw new InvalidArgumentException('OPUS_ADMIN_ROUTE_REQUEST_IDENTITY_EMPTY');
        }

        foreach ($this->roles as $role) {
            if (!is_string($role) || $role === '') {
                throw new InvalidArgumentException('OPUS_ADMIN_ROUTE_REQUEST_ROLE_INVALID');
            }
        }

        foreach ($this->scopes as $scope) {
            if (!is_string($scope) || $scope === '') {
                throw new InvalidArgumentException('OPUS_ADMIN_ROUTE_REQUEST_SCOPE_INVALID');
            }
        }
    }

    /** @param list<string> $scopes */
    public static function adminGet(string $path, string $site, string $identityContext, array $scopes): self
    {
        return new self('GET', $path, $site, $identityContext, ['admin'], $scopes);
    }

    public static function anonymousGet(string $path, string $site): self
    {
        return new self('GET', $path, $site, 'anonymous_public', [], []);
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

    public function identityContext(): string
    {
        return $this->identityContext;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
