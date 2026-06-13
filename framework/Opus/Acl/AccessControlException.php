<?php

declare(strict_types=1);

namespace Opus\Acl;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;
use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: ACL
 *   role: Class AccessControlException belongs to the ACL Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the ACL domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - acl-overview
 *   diagrams:
 *     - acl-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represents an explicit ACL contract failure.
 *
 * Responsibility:
 *   Carry stable ACL error codes for authorization contract failures.
 *
 * Contract:
 *   ACL must deny or fail explicitly. No implicit allow fallback.
 *
 * @package ASAP\Acl
 */
#[OpusRefBookClass(
    domain: 'ACL',
    role: 'Represent explicit ACL contract and authorization failures',
    responsibility: 'Provide stable ACL error codes and messages for authorization failures, contract failures and invalid contexts.',
    contracts: [
        'ACL failures use stable code prefixes.',
        'Unknown declarations fail explicitly.',
        'Access is never allowed implicitly after an ACL exception.',
    ],
    examples: ['acl-error'],
    diagrams: ['acl-runtime'],
    introducedIn: 'P112Q3E2'
)]
class AccessControlException extends RuntimeException implements RefBookInspectableInterface
{
    public const ROLE_UNKNOWN = 'ACL_ROLE_UNKNOWN';
    public const RESOURCE_UNKNOWN = 'ACL_RESOURCE_UNKNOWN';
    public const PRIVILEGE_UNKNOWN = 'ACL_PRIVILEGE_UNKNOWN';
    public const CONDITION_FAILED = 'ACL_CONDITION_FAILED';
    public const ACCESS_DENIED = 'ACL_ACCESS_DENIED';
    public const CONTEXT_INVALID = 'ACL_CONTEXT_INVALID';
    public const CONTRACT_FAILED = 'ACL_CONTRACT_FAILED';

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for ACL exceptions',
        behavior: 'Returns the stable RefBook domain used by scanners, snapshots and OPUS_REF_BOOK renderers.',
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
     * @param string $codeName Stable ACL error code.
     * @param string $detail Human-readable failure detail.
     *
     * @return static
     */
    #[OpusRefBookMethod(
        role: 'Create an ACL exception with a stable contract code',
        behavior: 'Builds an exception whose message begins with the stable ACL error code followed by a human-readable detail.',
        preconditions: ['The code name is a stable ACL error code.', 'The detail describes the explicit failure.'],
        postconditions: ['The returned exception keeps the stable code as message prefix.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-error'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public static function contract(string $codeName, string $detail): static
    {
        return new static($codeName . ': ' . $detail);
    }
}
