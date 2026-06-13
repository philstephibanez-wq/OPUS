<?php

declare(strict_types=1);

namespace Opus\Acl;

use ASAP\RefBook\Attribute\OpusRefBookClass;

/*
 * OPUS_REFBOOK:
 *   domain: ACL
 *   role: Class AccessDeniedException belongs to the ACL Opus framework domain.
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
 *   Represents an explicit ACL denial.
 *
 * Responsibility:
 *   Separate access denial from generic ACL contract failures.
 *
 * Contract:
 *   Denial must be explicit and inspectable.
 *
 * @package ASAP\Acl
 */
#[OpusRefBookClass(
    domain: 'ACL',
    role: 'Represent an explicit access-denied condition',
    responsibility: 'Distinguish authorization denial from generic ACL contract failures while preserving the ACL exception hierarchy.',
    contracts: [
        'Access denial must be explicit and inspectable.',
        'Denied access must never be converted to an implicit allow.',
        'The exception remains part of the ACL domain and inherited ACL error contract.',
    ],
    examples: ['acl-error'],
    diagrams: ['acl-runtime'],
    introducedIn: 'P112Q3E2A'
)]
final class AccessDeniedException extends AccessControlException
{
}
