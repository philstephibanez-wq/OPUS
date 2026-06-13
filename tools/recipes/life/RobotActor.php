<?php

declare(strict_types=1);

namespace Opus\Recipe\Life;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent one robotized user or system actor in Opus life recipes.
 *
 * Responsibility:
 *   Carry actor id, role, locale and explicit permissions without reading a
 *   real session, cookie or database account.
 *
 * Contract:
 *   Life scenarios are deterministic. Actor identity is simulated in memory and
 *   never mutates real users.
 */
final class RobotActor
{
    /** @param string[] $permissions */
    public function __construct(
        public readonly string $id,
        public readonly string $role,
        public readonly string $locale,
        public readonly array $permissions = []
    ) {
    }

    /** PUBLIC API: check an explicit simulated permission. */
    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
