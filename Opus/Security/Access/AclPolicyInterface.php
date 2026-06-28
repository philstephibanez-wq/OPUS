<?php
declare(strict_types=1);

namespace Opus\Security\Access;

use Opus\Security\Identity\IdentityContextInterface;

/**
 * Contract for OPUS ACL policy engines.
 */
interface AclPolicyInterface
{
    public function decide(string $policyId, IdentityContextInterface $identity): AccessDecisionInterface;
}
