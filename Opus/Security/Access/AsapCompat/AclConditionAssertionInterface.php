<?php
declare(strict_types=1);

namespace Opus\Security\Access\AsapCompat;

use Opus\Security\Identity\IdentityContextInterface;

/**
 * Contract for ACL conditional assertions.
 */
interface AclConditionAssertionInterface
{
    public function supports(string $type): bool;

    /** @param array<string,mixed> $condition */
    public function evaluate(array $condition, IdentityContextInterface $identity): bool;
}
