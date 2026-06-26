<?php
declare(strict_types=1);

namespace Opus\Security;

/**
 * Namespaced access-control helper for OPUS runtime security.
 *
 * Evaluates user roles and route policies for integrated application pages and API endpoints.
 */
final class Acl
 implements AclInterface {
    /** @param array<string,mixed> $page */
    public function canView(array $page): bool
    {
        $visibility = (string)($page['visibility'] ?? 'public');
        return $visibility === 'public';
    }
}
