<?php

declare(strict_types=1);

namespace ASAP\Acl;

use ASAP\RefBook\Attribute\AsapRefBookClass;
use ASAP\RefBook\Attribute\AsapRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/*
 * ASAP_REFBOOK:
 *   domain: ACL
 *   role: Class AccessRule belongs to the ACL ASAP framework domain.
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
 * PUBLIC DTO
 *
 * Role:
 *   Defines one ACL allow/deny rule.
 *
 * Responsibility:
 *   Bind role + resource + privilege to an explicit allow or deny decision.
 *
 * Contract:
 *   Rules are explicit. No implicit allow fallback is allowed.
 *
 * @package ASAP\Acl
 */
#[AsapRefBookClass(
    domain: 'ACL',
    role: 'Define one explicit ACL allow or deny rule',
    responsibility: 'Bind a role, resource and privilege to a deterministic allow or deny outcome and an optional condition.',
    contracts: [
        'Rule identifiers are non-empty after trimming.',
        'The rule key is deterministic and stable.',
        'Conditions are optional declared objects and are never inferred dynamically.',
    ],
    examples: ['acl-overview'],
    diagrams: ['acl-runtime'],
    introducedIn: 'P112Q3E2'
)]
final class AccessRule implements RefBookInspectableInterface
{
    private string $role;
    private string $resource;
    private string $privilege;
    private bool $allow;
    private ?AccessConditionInterface $condition;

    #[AsapRefBookMethod(
        role: 'Expose the RefBook domain for ACL rules',
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
     * @param string $role Role identifier.
     * @param string $resource Resource identifier.
     * @param string $privilege Privilege identifier.
     * @param bool $allow True for allow, false for deny.
     * @param AccessConditionInterface|null $condition Optional declared condition.
     *
     * @throws AccessControlException When identifiers are empty.
     */
    #[AsapRefBookMethod(
        role: 'Create one immutable ACL rule definition',
        behavior: 'Normalizes the rule identifiers, validates that none is empty and stores the explicit allow/deny flag with an optional condition.',
        preconditions: ['Role, resource and privilege identifiers must not be empty after trimming.'],
        postconditions: [
            'The rule key is stable for role, resource and privilege.',
            'The allow flag and optional condition are retained without implicit fallback.',
        ],
        sideEffects: ['none'],
        errors: ['ACL_CONTRACT_FAILED'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-overview'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function __construct(string $role, string $resource, string $privilege, bool $allow, ?AccessConditionInterface $condition = null)
    {
        $role = trim($role);
        $resource = trim($resource);
        $privilege = trim($privilege);

        if ($role === '' || $resource === '' || $privilege === '') {
            throw AccessControlException::contract(AccessControlException::CONTRACT_FAILED, 'ACL rule identifiers must not be empty.');
        }

        $this->role = $role;
        $this->resource = $resource;
        $this->privilege = $privilege;
        $this->allow = $allow;
        $this->condition = $condition;
    }

    /**
     * PUBLIC API
     *
     * @return string Stable rule key.
     */
    #[AsapRefBookMethod(
        role: 'Return the stable ACL rule key',
        behavior: 'Combines role, resource and privilege identifiers into the canonical key used by AccessControl lookup.',
        preconditions: ['The rule has been constructed with validated identifiers.'],
        postconditions: ['The returned key is deterministic and uses role::resource::privilege format.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-overview'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function key(): string
    {
        return $this->role . '::' . $this->resource . '::' . $this->privilege;
    }

    /**
     * PUBLIC API
     *
     * @return bool True when the rule allows access.
     */
    #[AsapRefBookMethod(
        role: 'Expose whether this ACL rule allows access',
        behavior: 'Returns the immutable allow/deny flag used by AccessControl after declaration and optional condition validation.',
        preconditions: ['The rule has been constructed.'],
        postconditions: ['The returned boolean matches the rule declaration.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-overview'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function allows(): bool
    {
        return $this->allow;
    }

    /**
     * PUBLIC API
     *
     * @return AccessConditionInterface|null Optional declared condition.
     */
    #[AsapRefBookMethod(
        role: 'Expose the optional ACL rule condition',
        behavior: 'Returns the condition object explicitly attached to the rule or null when the rule is unconditional.',
        preconditions: ['The rule has been constructed.'],
        postconditions: ['No condition is created implicitly.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-condition'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function condition(): ?AccessConditionInterface
    {
        return $this->condition;
    }
}
