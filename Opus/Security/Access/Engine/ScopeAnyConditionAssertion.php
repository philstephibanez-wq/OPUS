<?php
declare(strict_types=1);

namespace Opus\Security\Access\Engine;

use Opus\Security\Identity\IdentityContextInterface;

/**
 * ACL assertion checking that the identity owns at least one configured scope.
 */
final class ScopeAnyConditionAssertion implements AclConditionAssertionInterface, ScopeAnyConditionAssertionInterface
{
    public function supports(string $type): bool
    {
        return $type === 'scope_any';
    }

    public function evaluate(array $condition, IdentityContextInterface $identity): bool
    {
        $requiredScopes = array_values(array_filter(array_map('strval', (array) ($condition['scopes'] ?? [])), static fn (string $scope): bool => $scope !== ''));
        if ($requiredScopes === []) {
            throw new \RuntimeException('OPUS_ACL_CONDITION_SCOPES_EMPTY');
        }

        return array_values(array_intersect($requiredScopes, $identity->scopes())) !== [];
    }
}
