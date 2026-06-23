<?php
declare(strict_types=1);

namespace Opus\Security;

final class Acl
{
    /** @param array<string,mixed> $page */
    public function canView(array $page): bool
    {
        $visibility = (string)($page['visibility'] ?? 'public');
        return $visibility === 'public';
    }
}
