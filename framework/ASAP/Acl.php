<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the small top-level `ASAP\Acl` compatibility surface.
 *
 * Contract:
 *   This is not the full ACL engine. It does not grant access implicitly.
 *   Full ACL decisions belong to `ASAP\ACL\AccessControl`.
 *
 * Since:
 *   P112P1
 */
final class Acl
{
    public function canView(bool $allowed = false): bool
    {
        return $allowed;
    }
}
