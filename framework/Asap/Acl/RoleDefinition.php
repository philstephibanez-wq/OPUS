<?php

declare(strict_types=1);

namespace ASAP\Acl;

use ASAP\RefBook\Attribute\AsapRefBookClass;
use ASAP\RefBook\Attribute\AsapRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/*
 * ASAP_REFBOOK:
 *   domain: ACL
 *   role: Class RoleDefinition belongs to the ACL ASAP framework domain.
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
 *   Defines a declared ACL role.
 *
 * Responsibility:
 *   Carry a stable role identifier.
 *
 * Contract:
 *   Role identifiers must be explicit non-empty strings.
 *
 * @package ASAP\Acl
 */
#[AsapRefBookClass(
    domain: 'ACL',
    role: 'Define one declared ACL role',
    responsibility: 'Carry the stable role identifier used by AccessControl declarations and rule lookup.',
    contracts: [
        'The role identifier is non-empty after trimming.',
        'The object is immutable after construction.',
        'The definition does not decide access by itself.',
    ],
    examples: ['acl-overview'],
    diagrams: ['acl-runtime'],
    introducedIn: 'P112Q3E2'
)]
final class RoleDefinition implements RefBookInspectableInterface
{
    private string $id;

    #[AsapRefBookMethod(
        role: 'Expose the RefBook domain for ACL role definitions',
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
     * @param string $id Stable role identifier.
     *
     * @throws AccessControlException When the role identifier is empty.
     */
    #[AsapRefBookMethod(
        role: 'Create an immutable ACL role definition',
        behavior: 'Normalizes the role identifier, validates that it is not empty and stores it for ACL declaration lookup.',
        preconditions: ['The provided role identifier must not be empty after trimming.'],
        postconditions: ['The role identifier is stable and non-empty.'],
        sideEffects: ['none'],
        errors: ['ACL_ROLE_UNKNOWN'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-overview'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function __construct(string $id)
    {
        $id = trim($id);

        if ($id === '') {
            throw AccessControlException::contract(AccessControlException::ROLE_UNKNOWN, 'Role id must not be empty.');
        }

        $this->id = $id;
    }

    /**
     * PUBLIC API
     *
     * @return string Stable role identifier.
     */
    #[AsapRefBookMethod(
        role: 'Return the stable ACL role identifier',
        behavior: 'Exposes the validated identifier used by AccessControl declarations and rule lookup.',
        preconditions: ['The definition has been constructed with a valid identifier.'],
        postconditions: ['The returned identifier is non-empty and unchanged.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-overview'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function id(): string
    {
        return $this->id;
    }
}
