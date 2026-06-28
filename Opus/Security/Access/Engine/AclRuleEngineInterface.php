<?php
declare(strict_types=1);

namespace Opus\Security\Access\Engine;

use Opus\Security\Access\AccessDecisionInterface;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * Contract for reusable OPUS ACL rule engines.
 */
interface AclRuleEngineInterface
{
    /**
     * @param array<string,mixed> $policy
     */
    public function decide(string $policyId, array $policy, IdentityContextInterface $identity): AccessDecisionInterface;
}
