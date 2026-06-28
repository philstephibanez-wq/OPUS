<?php
declare(strict_types=1);

namespace Opus\Security\Access;

use Opus\Security\Access\Engine\AclRuleEngineInterface;
use Opus\Security\Access\Engine\HierarchicalAclEngine;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * Configuration-backed OPUS ACL policy engine.
 *
 * The default engine is hierarchical and rule-based: roles, resources, privileges, allow/deny
 * rules, role inheritance, resource inheritance, allRoles/allResources/allPrivileges
 * and conditional assertions are evaluated before the final AccessDecision is emitted.
 */
final class ConfigAclPolicy implements AclPolicyInterface
{
    /** @var array<string,mixed> */
    private array $config;
    /** @var array<string,array<string,mixed>> */
    private array $policies;
    private AclRuleEngineInterface $engine;

    /**
     * @param array<string,mixed> $config
     * @param array<string,array<string,mixed>> $policies
     */
    private function __construct(array $config, array $policies, AclRuleEngineInterface $engine)
    {
        $this->config = $config;
        $this->policies = $policies;
        $this->engine = $engine;
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

        return new self($decoded, $policies, HierarchicalAclEngine::fromConfig($decoded));
    }

    public function decide(string $policyId, IdentityContextInterface $identity): AccessDecisionInterface
    {
        if (!array_key_exists($policyId, $this->policies)) {
            throw new \RuntimeException('OPUS_ACL_POLICY_NOT_FOUND: ' . $policyId);
        }

        $policy = $this->policies[$policyId];
        if (isset($policy['resource']) || isset($policy['privilege'])) {
            return $this->engine->decide($policyId, $policy, $identity);
        }

        return $this->decideLegacyPolicy($policyId, $policy, $identity);
    }

    /** @return array<string,mixed> */
    public function export(): array
    {
        return [
            'contract' => $this->config['contract'] ?? 'OPUS_ACL_POLICY_REGISTRY_V1',
            'engine' => $this->config['engine'] ?? 'hierarchical_acl',
            'roles' => $this->config['roles'] ?? [],
            'resources' => $this->config['resources'] ?? [],
            'policies' => $this->policies,
            'rules' => $this->config['rules'] ?? [],
        ];
    }

    /**
     * Compatibility shim for older configs. New OPUS configs must use resource /
     * privilege policies evaluated by the hierarchical OPUS ACL engine.
     *
     * @param array<string,mixed> $policy
     */
    private function decideLegacyPolicy(string $policyId, array $policy, IdentityContextInterface $identity): AccessDecisionInterface
    {
        $access = (string) ($policy['access'] ?? '');
        if ($access === 'public') {
            return AccessDecision::granted('OPUS_ACL_PUBLIC_ACCESS_GRANTED', ['policy' => $policyId, 'legacy_access' => true]);
        }

        if ($access === 'authenticated') {
            return $identity->isAnonymous()
                ? AccessDecision::denied('OPUS_ACL_AUTHENTICATION_REQUIRED', ['policy' => $policyId, 'legacy_access' => true])
                : AccessDecision::granted('OPUS_ACL_AUTHENTICATED_ACCESS_GRANTED', ['policy' => $policyId, 'legacy_access' => true]);
        }

        if ($access === 'role_or_scope') {
            if ($identity->isAnonymous()) {
                return AccessDecision::denied('OPUS_ACL_AUTHENTICATION_REQUIRED', ['policy' => $policyId, 'legacy_access' => true]);
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
                    'legacy_access' => true,
                ]);
            }

            return AccessDecision::denied('OPUS_ACL_POLICY_DENIED', [
                'policy' => $policyId,
                'required_roles' => $allowedRoles,
                'required_scopes' => $allowedScopes,
                'legacy_access' => true,
            ]);
        }

        throw new \RuntimeException('OPUS_ACL_POLICY_ACCESS_UNSUPPORTED: ' . $access);
    }
}
