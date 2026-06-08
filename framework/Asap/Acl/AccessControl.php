<?php

declare(strict_types=1);

namespace ASAP\Acl;

use ASAP\RefBook\Attribute\AsapRefBookClass;
use ASAP\RefBook\Attribute\AsapRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/*
 * ASAP_REFBOOK:
 *   domain: ACL
 *   role: Class AccessControl belongs to the ACL ASAP framework domain.
 *   contract:
 *     - keeps responsibility limited to the ACL domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - acl-overview
 *   diagrams:
 *     - acl-runtime
 * END_ASAP_REFBOOK
 */
/**
 * PUBLIC CLASS
 *
 * Role:
 *   Evaluate explicit ACL rules.
 *
 * Responsibility:
 *   Decide whether a role can use one privilege on one resource in a validated context.
 *
 * Contract:
 *   No singleton. No implicit allow. No Reflection condition fallback.
 *
 * @package ASAP\Acl
 */
#[AsapRefBookClass(
    domain: 'ACL',
    role: 'Evaluate explicit access-control rules',
    responsibility: 'Own the ACL decision engine that validates declared roles, resources, privileges and rules before returning an access decision.',
    contracts: [
        'Unknown role, resource or privilege fails explicitly.',
        'A missing rule denies access explicitly and never allows by fallback.',
        'Conditions are declared objects and are evaluated only through AccessConditionInterface.',
    ],
    examples: ['acl-overview'],
    diagrams: ['acl-runtime'],
    introducedIn: 'P112Q3E2'
)]
final class AccessControl implements RefBookInspectableInterface
{
    /** @var array<string,RoleDefinition> */
    private array $roles = [];

    /** @var array<string,ResourceDefinition> */
    private array $resources = [];

    /** @var array<string,PrivilegeDefinition> */
    private array $privileges = [];

    /** @var array<string,AccessRule> */
    private array $rules = [];

    #[AsapRefBookMethod(
        role: 'Expose the RefBook domain for ACL decision engines',
        behavior: 'Returns the stable RefBook domain used by scanners, snapshots and ASAP_REF_BOOK renderers.',
        preconditions: ['none'],
        postconditions: ['The returned domain is ACL.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-refbook-domain'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public static function refBookDomain(): string
    {
        return 'ACL';
    }

    /**
     * PUBLIC API
     *
     * @param RoleDefinition[] $roles Declared roles.
     * @param ResourceDefinition[] $resources Declared resources.
     * @param PrivilegeDefinition[] $privileges Declared privileges.
     * @param AccessRule[] $rules Explicit ACL rules.
     *
     * @throws AccessControlException When one declaration has an invalid type.
     */
    #[AsapRefBookMethod(
        role: 'Create an ACL decision engine from explicit declarations',
        behavior: 'Indexes declared roles, resources, privileges and rules after checking that every declaration uses the expected ACL object type.',
        preconditions: [
            'Every role entry is a RoleDefinition.',
            'Every resource entry is a ResourceDefinition.',
            'Every privilege entry is a PrivilegeDefinition.',
            'Every rule entry is an AccessRule.',
        ],
        postconditions: [
            'Declarations are indexed by stable identifiers or rule keys.',
            'No implicit role, resource, privilege or rule is created.',
        ],
        sideEffects: ['Mutates only the new AccessControl instance during construction.'],
        errors: ['ACL_CONTRACT_FAILED'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-overview'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function __construct(array $roles, array $resources, array $privileges, array $rules)
    {
        foreach ($roles as $role) {
            if (!$role instanceof RoleDefinition) {
                throw AccessControlException::contract(AccessControlException::CONTRACT_FAILED, 'Roles must be RoleDefinition instances.');
            }

            $this->roles[$role->id()] = $role;
        }

        foreach ($resources as $resource) {
            if (!$resource instanceof ResourceDefinition) {
                throw AccessControlException::contract(AccessControlException::CONTRACT_FAILED, 'Resources must be ResourceDefinition instances.');
            }

            $this->resources[$resource->id()] = $resource;
        }

        foreach ($privileges as $privilege) {
            if (!$privilege instanceof PrivilegeDefinition) {
                throw AccessControlException::contract(AccessControlException::CONTRACT_FAILED, 'Privileges must be PrivilegeDefinition instances.');
            }

            $this->privileges[$privilege->id()] = $privilege;
        }

        foreach ($rules as $rule) {
            if (!$rule instanceof AccessRule) {
                throw AccessControlException::contract(AccessControlException::CONTRACT_FAILED, 'Rules must be AccessRule instances.');
            }

            $this->rules[$rule->key()] = $rule;
        }
    }

    /**
     * PUBLIC API
     *
     * Role:
     *   Decide whether access is allowed.
     *
     * @param string $role Role identifier.
     * @param string $resource Resource identifier.
     * @param string $privilege Privilege identifier.
     * @param AccessContext|null $context Optional validated access context.
     *
     * @return AccessDecision Access decision.
     *
     * @throws AccessControlException When role/resource/privilege is unknown.
     *
     * Contract:
     *   Missing rule denies access explicitly. Unknown declarations fail explicitly.
     */
    #[AsapRefBookMethod(
        role: 'Return an explicit allow or deny decision for one ACL request',
        behavior: 'Validates the requested role, resource and privilege, selects the matching explicit rule, evaluates the optional rule condition and returns a structured AccessDecision.',
        preconditions: [
            'The role identifier must match a declared role.',
            'The resource identifier must match a declared resource.',
            'The privilege identifier must match a declared privilege.',
        ],
        postconditions: [
            'A matching allow rule returns an allowed AccessDecision when any condition passes.',
            'A missing rule returns a denied AccessDecision with an explicit reason.',
            'Unknown declarations throw explicit ACL contract exceptions.',
        ],
        sideEffects: ['none'],
        errors: ['ACL_ROLE_UNKNOWN', 'ACL_RESOURCE_UNKNOWN', 'ACL_PRIVILEGE_UNKNOWN', 'ACL_ACCESS_DENIED', 'ACL_CONDITION_FAILED'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-overview'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function decide(string $role, string $resource, string $privilege, ?AccessContext $context = null): AccessDecision
    {
        if (!isset($this->roles[$role])) {
            throw AccessControlException::contract(AccessControlException::ROLE_UNKNOWN, 'Unknown role: ' . $role);
        }

        if (!isset($this->resources[$resource])) {
            throw AccessControlException::contract(AccessControlException::RESOURCE_UNKNOWN, 'Unknown resource: ' . $resource);
        }

        if (!isset($this->privileges[$privilege])) {
            throw AccessControlException::contract(AccessControlException::PRIVILEGE_UNKNOWN, 'Unknown privilege: ' . $privilege);
        }

        $key = $role . '::' . $resource . '::' . $privilege;

        if (!isset($this->rules[$key])) {
            return new AccessDecision(false, AccessControlException::ACCESS_DENIED . ': No explicit rule.');
        }

        $rule = $this->rules[$key];

        if ($rule->condition() !== null && !$rule->condition()->allows($context ?? new AccessContext())) {
            return new AccessDecision(false, AccessControlException::CONDITION_FAILED . ': Condition refused.');
        }

        return new AccessDecision($rule->allows(), $rule->allows() ? 'ACL_ALLOWED' : AccessControlException::ACCESS_DENIED);
    }
}
