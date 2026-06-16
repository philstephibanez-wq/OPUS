<?php

declare(strict_types=1);

namespace Opus\Admin;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent a native OPUS administrator dashboard action request.
 *
 * Responsibility:
 *   Carry only the action intent, identity context, roles and scopes required by
 *   the admin dashboard action control plane.
 *
 * Contract:
 *   This object never executes the action and never decides authorization. Any
 *   invalid shape is an internal diagnostic and must not be exposed publicly.
 */
final class AdminDashboardActionRequest
{
    /**
     * @param list<string> $roles
     * @param list<string> $scopes
     */
    public function __construct(
        private readonly string $action,
        private readonly string $site,
        private readonly string $identityContext,
        private readonly array $roles,
        private readonly array $scopes
    ) {
        if ($this->action === '') {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_REQUEST_ACTION_EMPTY');
        }

        if ($this->site === '') {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_REQUEST_SITE_EMPTY');
        }

        if ($this->identityContext === '') {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_REQUEST_IDENTITY_EMPTY');
        }

        foreach ($this->roles as $role) {
            if (!is_string($role) || $role === '') {
                throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_REQUEST_ROLE_INVALID');
            }
        }

        foreach ($this->scopes as $scope) {
            if (!is_string($scope) || $scope === '') {
                throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_REQUEST_SCOPE_INVALID');
            }
        }
    }

    /** @param list<string> $scopes */
    public static function admin(string $action, string $site, string $identityContext, array $scopes): self
    {
        return new self($action, $site, $identityContext, ['admin'], $scopes);
    }

    public static function anonymous(string $action, string $site): self
    {
        return new self($action, $site, 'anonymous_public', [], []);
    }

    public function action(): string
    {
        return $this->action;
    }

    public function site(): string
    {
        return $this->site;
    }

    public function identityContext(): string
    {
        return $this->identityContext;
    }

    public function routeKey(): string
    {
        return 'POST /admin/actions/' . strtolower(str_replace('_', '-', $this->action));
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
