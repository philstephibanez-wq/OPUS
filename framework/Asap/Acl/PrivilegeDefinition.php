<?php

declare(strict_types=1);

namespace ASAP\Acl;

use ASAP\RefBook\Attribute\AsapRefBookClass;
use ASAP\RefBook\Attribute\AsapRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/*
 * ASAP_REFBOOK:
 *   domain: ACL
 *   role: Class PrivilegeDefinition belongs to the ACL ASAP framework domain.
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
 *   Defines a declared ACL privilege.
 *
 * Responsibility:
 *   Carry a stable privilege identifier.
 *
 * Contract:
 *   Privilege identifiers must be explicit non-empty strings.
 *
 * @package ASAP\Acl
 */
#[AsapRefBookClass(
    domain: 'ACL',
    role: 'Define one declared ACL privilege',
    responsibility: 'Carry the stable privilege identifier used by AccessControl declarations and rule lookup.',
    contracts: [
        'The privilege identifier is non-empty after trimming.',
        'The object is immutable after construction.',
        'The definition does not decide access by itself.',
    ],
    examples: ['acl-overview'],
    diagrams: ['acl-runtime'],
    introducedIn: 'P112Q3E2'
)]
final class PrivilegeDefinition implements RefBookInspectableInterface
{
    private string $id;

    #[AsapRefBookMethod(
        role: 'Expose the RefBook domain for ACL privilege definitions',
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
     * @param string $id Stable privilege identifier.
     *
     * @throws AccessControlException When the privilege identifier is empty.
     */
    #[AsapRefBookMethod(
        role: 'Create an immutable ACL privilege definition',
        behavior: 'Normalizes the privilege identifier, validates that it is not empty and stores it for ACL declaration lookup.',
        preconditions: ['The provided privilege identifier must not be empty after trimming.'],
        postconditions: ['The privilege identifier is stable and non-empty.'],
        sideEffects: ['none'],
        errors: ['ACL_PRIVILEGE_UNKNOWN'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-overview'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function __construct(string $id)
    {
        $id = trim($id);

        if ($id === '') {
            throw AccessControlException::contract(AccessControlException::PRIVILEGE_UNKNOWN, 'Privilege id must not be empty.');
        }

        $this->id = $id;
    }

    /**
     * PUBLIC API
     *
     * @return string Stable privilege identifier.
     */
    #[AsapRefBookMethod(
        role: 'Return the stable ACL privilege identifier',
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
