<?php
declare(strict_types=1);

namespace Opus\Security\Access;

use Opus\Security\Identity\IdentityContextInterface;

/**
 * Configuration-backed ACL policy engine.
 */
final class ConfigAclPolicy implements AclPolicyInterface
{
    /** @var array<string,array<string,mixed>> */
    private array $policies;

    /** @param array<string,array<string,mixed>> $policies */
    private function __construct(array $policies)
    {
        $this->policies = $policies;
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_ACL_POLICY_CONFIG_MISSING: ' . $path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OPUS_ACL_POLICY_CONFIG_JSON_INVALID: ' . $path);
        }
        if (($decoded['contract'] ?? '') !== 'OPUS_ACL_POLICY_REGISTRY_V1') {
            throw new \RuntimeException('OPUS_ACL_POLICY_CONFIG_CONTRACT_INVALID: ' . $path);
        }

        $policies = [];
        foreach ((array) ($decoded['policies'] ?? []) as $id => $policy) {
            if (!is_array($policy)) {
                throw new \RuntimeException('OPUS_ACL_POLICY_INVALID: ' . (string) $id);
            }
            $policies[(string) $id] = $policy;
        }

        return new self($policies);
    }

    public function decide(string $policyId, IdentityContextInterface $identity): AccessDecisionInterface
    {
        if (!array_key_exists($policyId, $this->policies)) {
            throw new \RuntimeException('OPUS_ACL_POLICY_NOT_FOUND: ' . $policyId);
        }

        $policy = $this->policies[$policyId];
        $access = (string) ($policy['access'] ?? '');
        if ($access === 'public') {
            return AccessDecision::granted('OPUS_ACL_PUBLIC_ACCESS_GRANTED', ['policy' => $policyId]);
        }

        if ($access === 'authenticated') {
            return $identity->isAnonymous()
                ? AccessDecision::denied('OPUS_ACL_AUTHENTICATION_REQUIRED', ['policy' => $policyId])
                : AccessDecision::granted('OPUS_ACL_AUTHENTICATED_ACCESS_GRANTED', ['policy' => $policyId]);
        }

        if ($access === 'role_or_scope') {
            if ($identity->isAnonymous()) {
                return AccessDecision::denied('OPUS_ACL_AUTHENTICATION_REQUIRED', ['policy' => $policyId]);
            }

            $allowedRoles = array_values(array_map('strval', (array) ($policy['roles'] ?? [])));
            $allowedScopes = array_values(array_map('strval', (array) ($policy['scopes'] ?? [])));
            $roleMatch = array_values(array_intersect($allowedRoles, $identity->roles()));
            $scopeMatch = array_values(array_intersect($allowedScopes, $identity->scopes()));

            if ($roleMatch || $scopeMatch) {
                return AccessDecision::granted('OPUS_ACL_POLICY_MATCHED', [
                    'policy' => $policyId,
                    'roles' => $roleMatch,
                    'scopes' => $scopeMatch,
                ]);
            }

            return AccessDecision::denied('OPUS_ACL_POLICY_DENIED', [
                'policy' => $policyId,
                'required_roles' => $allowedRoles,
                'required_scopes' => $allowedScopes,
            ]);
        }

        throw new \RuntimeException('OPUS_ACL_POLICY_ACCESS_UNSUPPORTED: ' . $access);
    }

    /** @return array<string,array<string,mixed>> */
    public function export(): array
    {
        return $this->policies;
    }
}
