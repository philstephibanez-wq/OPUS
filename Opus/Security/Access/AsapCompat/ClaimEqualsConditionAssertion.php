<?php
declare(strict_types=1);

namespace Opus\Security\Access\AsapCompat;

use Opus\Security\Identity\IdentityContextInterface;

/**
 * ACL assertion checking that an identity claim equals an expected value.
 */
final class ClaimEqualsConditionAssertion implements AclConditionAssertionInterface
{
    public function supports(string $type): bool
    {
        return $type === 'claim_equals';
    }

    public function evaluate(array $condition, IdentityContextInterface $identity): bool
    {
        $claim = (string) ($condition['claim'] ?? '');
        if ($claim === '') {
            throw new \RuntimeException('OPUS_ACL_CONDITION_CLAIM_MISSING');
        }

        $claims = $identity->claims();
        if (!array_key_exists($claim, $claims)) {
            return false;
        }

        return $claims[$claim] === ($condition['equals'] ?? null);
    }
}
