<?php
declare(strict_types=1);

namespace Opus\Security\Acl;

final class AclDecision implements AclDecisionInterface
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $code,
        public readonly string $resource,
        public readonly string $action
    ) {
    }
}
