<?php
declare(strict_types=1);

namespace Opus\Security\Access\Engine;

use Opus\Security\Access\AccessDecision;
use Opus\Security\Access\AccessDecisionInterface;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * Hierarchical OPUS ACL rule engine.
 *
 * Supports role/resource inheritance, privileges, allow/deny rules, wildcard roles,
 * wildcard resources, wildcard privileges, conditional assertions and default deny.
 */
final class HierarchicalAclEngine implements AclRuleEngineInterface
{
    /** @var array<string,mixed> */
    private array $config;
    /** @var array<string,array<string,mixed>> */
    private array $roles;
    /** @var array<string,array<string,mixed>> */
    private array $resources;
    /** @var list<array<string,mixed>> */
    private array $rules;
    /** @var list<AclConditionAssertionInterface> */
    private array $assertions;

    /** @param array<string,mixed> $config */
    private function __construct(array $config)
    {
        $this->config = $config;
        $this->roles = $this->normalizeMap((array) ($config['roles'] ?? []), 'OPUS_ACL_ROLES_INVALID');
        $this->resources = $this->normalizeMap((array) ($config['resources'] ?? []), 'OPUS_ACL_RESOURCES_INVALID');
        $this->rules = $this->normalizeRules((array) ($config['rules'] ?? []));
        $this->assertions = [
            new ScopeAnyConditionAssertion(),
            new ClaimEqualsConditionAssertion(),
        ];
    }

    /** @param array<string,mixed> $config */
    public static function fromConfig(array $config): self
    {
        return new self($config);
    }

    /**
     * @param array<string,mixed> $policy
     */
    public function decide(string $policyId, array $policy, IdentityContextInterface $identity): AccessDecisionInterface
    {
        $resource = (string) ($policy['resource'] ?? '');
        $privilege = (string) ($policy['privilege'] ?? '');
        if ($resource === '') {
            throw new \RuntimeException('OPUS_ACL_POLICY_RESOURCE_MISSING: ' . $policyId);
        }
        if ($privilege === '') {
            throw new \RuntimeException('OPUS_ACL_POLICY_PRIVILEGE_MISSING: ' . $policyId);
        }
        if (!array_key_exists($resource, $this->resources)) {
            throw new \RuntimeException('OPUS_ACL_POLICY_RESOURCE_UNKNOWN: ' . $policyId . ':' . $resource);
        }

        $effectiveRoles = $this->effectiveRoles($identity);
        $resourceLineage = $this->resourceLineage($resource);
        $trace = [];
        $lastMatchedRule = null;

        foreach ($this->rules as $index => $rule) {
            $effect = (string) ($rule['effect'] ?? '');
            if ($effect !== 'allow' && $effect !== 'deny') {
                throw new \RuntimeException('OPUS_ACL_RULE_EFFECT_UNSUPPORTED: ' . (string) $index . ':' . $effect);
            }

            $roleMatch = $this->matchesRole($rule['roles'] ?? [], $effectiveRoles);
            $resourceMatch = $this->matchesResource($rule['resources'] ?? [], $resourceLineage, $index);
            $privilegeMatch = $this->matchesPrivilege($rule['privileges'] ?? [], $privilege);
            $conditionsMatch = $this->conditionsMatch((array) ($rule['conditions'] ?? []), $identity);
            $matched = $roleMatch && $resourceMatch && $privilegeMatch && $conditionsMatch;

            $trace[] = [
                'index' => $index,
                'effect' => $effect,
                'description' => (string) ($rule['description'] ?? ''),
                'role_match' => $roleMatch,
                'resource_match' => $resourceMatch,
                'privilege_match' => $privilegeMatch,
                'conditions_match' => $conditionsMatch,
                'matched' => $matched,
            ];

            if ($matched) {
                $lastMatchedRule = [
                    'index' => $index,
                    'effect' => $effect,
                    'description' => (string) ($rule['description'] ?? ''),
                ];
            }
        }

        $context = [
            'policy' => $policyId,
            'resource' => $resource,
            'privilege' => $privilege,
            'effective_roles' => $effectiveRoles,
            'resource_lineage' => $resourceLineage,
            'trace' => $trace,
        ];

        if ($lastMatchedRule === null) {
            return AccessDecision::denied('OPUS_ACL_DEFAULT_DENY', $context);
        }

        $context['matched_rule'] = $lastMatchedRule;
        if ($lastMatchedRule['effect'] === 'deny') {
            return AccessDecision::denied('OPUS_ACL_RULE_DENIED', $context);
        }

        return AccessDecision::granted('OPUS_ACL_RULE_ALLOWED', $context);
    }

    /**
     * @param array<string,mixed> $map
     * @return array<string,array<string,mixed>>
     */
    private function normalizeMap(array $map, string $errorCode): array
    {
        $normalized = [];
        foreach ($map as $id => $definition) {
            if (!is_array($definition)) {
                throw new \RuntimeException($errorCode . ': ' . (string) $id);
            }
            $normalized[(string) $id] = $definition;
        }

        return $normalized;
    }

    /**
     * @param array<int|string,mixed> $rules
     * @return list<array<string,mixed>>
     */
    private function normalizeRules(array $rules): array
    {
        $normalized = [];
        foreach ($rules as $index => $rule) {
            if (!is_array($rule)) {
                throw new \RuntimeException('OPUS_ACL_RULE_INVALID: ' . (string) $index);
            }
            $normalized[] = $rule;
        }

        return $normalized;
    }

    /** @return list<string> */
    private function effectiveRoles(IdentityContextInterface $identity): array
    {
        $seed = $identity->isAnonymous() ? ['visitor'] : ['authenticated'];
        foreach ($identity->roles() as $role) {
            $seed[] = $role;
        }

        $expanded = [];
        foreach ($seed as $role) {
            $this->expandRole((string) $role, $expanded, []);
        }

        return array_values(array_unique(array_filter($expanded, static fn (string $role): bool => $role !== '')));
    }

    /**
     * @param list<string> $expanded
     * @param list<string> $stack
     */
    private function expandRole(string $role, array &$expanded, array $stack): void
    {
        if (in_array($role, $stack, true)) {
            throw new \RuntimeException('OPUS_ACL_ROLE_INHERITANCE_CYCLE: ' . implode('>', $stack) . '>' . $role);
        }
        if (!in_array($role, $expanded, true)) {
            $expanded[] = $role;
        }

        $parents = array_values(array_filter(array_map('strval', (array) ($this->roles[$role]['parents'] ?? [])), static fn (string $parent): bool => $parent !== ''));
        foreach ($parents as $parent) {
            $this->expandRole($parent, $expanded, array_merge($stack, [$role]));
        }
    }

    /** @return list<string> */
    private function resourceLineage(string $resource): array
    {
        $lineage = [];
        $current = $resource;
        $stack = [];
        while ($current !== '') {
            if (in_array($current, $stack, true)) {
                throw new \RuntimeException('OPUS_ACL_RESOURCE_INHERITANCE_CYCLE: ' . implode('>', $stack) . '>' . $current);
            }
            if (!array_key_exists($current, $this->resources)) {
                throw new \RuntimeException('OPUS_ACL_RESOURCE_UNKNOWN: ' . $current);
            }
            $lineage[] = $current;
            $stack[] = $current;
            $parent = $this->resources[$current]['parent'] ?? null;
            $current = is_string($parent) ? $parent : '';
        }

        return $lineage;
    }

    /** @param list<string> $effectiveRoles */
    private function matchesRole(mixed $ruleRoles, array $effectiveRoles): bool
    {
        $roles = $this->normalizeSelector($ruleRoles);
        return in_array('*', $roles, true) || array_values(array_intersect($roles, $effectiveRoles)) !== [];
    }

    /** @param list<string> $resourceLineage */
    private function matchesResource(mixed $ruleResources, array $resourceLineage, int $ruleIndex): bool
    {
        $resources = $this->normalizeSelector($ruleResources);
        if (in_array('*', $resources, true)) {
            return true;
        }
        foreach ($resources as $resource) {
            if (!array_key_exists($resource, $this->resources)) {
                throw new \RuntimeException('OPUS_ACL_RULE_RESOURCE_UNKNOWN: ' . (string) $ruleIndex . ':' . $resource);
            }
        }

        return array_values(array_intersect($resources, $resourceLineage)) !== [];
    }

    private function matchesPrivilege(mixed $rulePrivileges, string $privilege): bool
    {
        $privileges = $this->normalizeSelector($rulePrivileges);
        return in_array('*', $privileges, true) || in_array($privilege, $privileges, true);
    }

    /** @param array<int|string,mixed> $conditions */
    private function conditionsMatch(array $conditions, IdentityContextInterface $identity): bool
    {
        foreach ($conditions as $index => $condition) {
            if (!is_array($condition)) {
                throw new \RuntimeException('OPUS_ACL_CONDITION_INVALID: ' . (string) $index);
            }
            $type = (string) ($condition['type'] ?? '');
            if ($type === '') {
                throw new \RuntimeException('OPUS_ACL_CONDITION_TYPE_MISSING: ' . (string) $index);
            }

            $assertion = $this->assertionFor($type);
            if (!$assertion->evaluate($condition, $identity)) {
                return false;
            }
        }

        return true;
    }

    private function assertionFor(string $type): AclConditionAssertionInterface
    {
        foreach ($this->assertions as $assertion) {
            if ($assertion->supports($type)) {
                return $assertion;
            }
        }

        throw new \RuntimeException('OPUS_ACL_CONDITION_UNSUPPORTED: ' . $type);
    }

    /** @return list<string> */
    private function normalizeSelector(mixed $value): array
    {
        if ($value === '*') {
            return ['*'];
        }
        if (!is_array($value)) {
            return [(string) $value];
        }

        return array_values(array_filter(array_map('strval', $value), static fn (string $item): bool => $item !== ''));
    }
}
