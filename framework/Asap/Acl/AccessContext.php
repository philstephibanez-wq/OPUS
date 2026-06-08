<?php

declare(strict_types=1);

namespace ASAP\Acl;

use ASAP\RefBook\Attribute\AsapRefBookClass;
use ASAP\RefBook\Attribute\AsapRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/*
 * ASAP_REFBOOK:
 *   domain: ACL
 *   role: Class AccessContext belongs to the ACL ASAP framework domain.
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
 *   Carries contextual values for ACL condition evaluation.
 *
 * Responsibility:
 *   Provide explicit key/value context to declared AccessConditionInterface instances.
 *
 * Contract:
 *   Context keys must be explicit. ACL must not read globals silently.
 *
 * @package ASAP\Acl
 */
#[AsapRefBookClass(
    domain: 'ACL',
    role: 'Carry explicit contextual values for ACL condition evaluation',
    responsibility: 'Provide declared AccessConditionInterface implementations with controlled key/value data instead of hidden global state.',
    contracts: [
        'Context values are supplied explicitly by the caller.',
        'Missing keys fail explicitly when read.',
        'ACL conditions must not read globals silently.',
    ],
    examples: ['acl-condition'],
    diagrams: ['acl-runtime'],
    introducedIn: 'P112Q3E2'
)]
final class AccessContext implements RefBookInspectableInterface
{
    /** @var array<string,mixed> */
    private array $values;

    #[AsapRefBookMethod(
        role: 'Expose the RefBook domain for ACL contexts',
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
     * @param array<string,mixed> $values Context values.
     */
    #[AsapRefBookMethod(
        role: 'Create an ACL context from explicit values',
        behavior: 'Stores caller-provided key/value data that ACL conditions may inspect during authorization.',
        preconditions: ['The values array is supplied explicitly by the caller.'],
        postconditions: ['The context stores the provided values without reading external state.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-condition'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    /**
     * PUBLIC API
     *
     * @param string $key Context key.
     *
     * @return bool True when the key exists.
     */
    #[AsapRefBookMethod(
        role: 'Check whether an ACL context key exists',
        behavior: 'Returns true when the supplied key is present in the explicit context payload.',
        preconditions: ['The context has been constructed.'],
        postconditions: ['The context payload is not modified.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-condition'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * PUBLIC API
     *
     * @param string $key Context key.
     *
     * @return mixed Context value.
     *
     * @throws AccessControlException When the key is missing.
     */
    #[AsapRefBookMethod(
        role: 'Read one ACL context value explicitly',
        behavior: 'Returns the context value for an existing key and fails explicitly when the key is absent.',
        preconditions: ['The key should be present in the explicit context payload.'],
        postconditions: ['The returned value is the value stored for the requested key.'],
        sideEffects: ['none'],
        errors: ['ACL_CONTEXT_INVALID'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-condition'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw AccessControlException::contract(AccessControlException::CONTEXT_INVALID, 'Context key not found: ' . $key);
        }

        return $this->values[$key];
    }
}
