<?php

declare(strict_types=1);

namespace Opus\Acl;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/*
 * OPUS_REFBOOK:
 *   domain: ACL
 *   role: Class Acl belongs to the ACL Opus framework domain.
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
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the small top-level `ASAP\Acl` compatibility surface.
 *
 * Contract:
 *   This is not the full ACL engine. It does not grant access implicitly.
 *   Full ACL decisions belong to `ASAP\Acl\AccessControl`.
 *
 * Since:
 *   P112P1
 */
#[OpusRefBookClass(
    domain: 'ACL',
    role: 'Expose the legacy top-level ACL compatibility surface',
    responsibility: 'Keep a minimal public ACL facade available while directing real authorization decisions to AccessControl.',
    contracts: [
        'This compatibility shim must not replace AccessControl as the ACL engine.',
        'No implicit permission is granted by the facade.',
        'The facade remains documented so OPUS_REF_BOOK does not hide legacy public surface.',
    ],
    examples: ['acl-overview'],
    diagrams: ['acl-runtime'],
    introducedIn: 'P112Q3E2'
)]
final class Acl implements RefBookInspectableInterface
{
    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for the ACL compatibility facade',
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

    #[OpusRefBookMethod(
        role: 'Return the explicit compatibility view permission flag',
        behavior: 'Returns the provided boolean value without performing authorization logic or inferring permissions.',
        preconditions: ['The caller provides the explicit allowed flag, defaulting to false.'],
        postconditions: ['The returned value equals the provided allowed flag.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-overview'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function canView(bool $allowed = false): bool
    {
        return $allowed;
    }
}
