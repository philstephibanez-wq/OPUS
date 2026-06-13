<?php

declare(strict_types=1);

namespace Opus\Acl;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;

/*
 * OPUS_REFBOOK:
 *   domain: ACL
 *   role: Interface AccessConditionInterface belongs to the ACL Opus framework domain.
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
 * PUBLIC INTERFACE
 *
 * Role:
 *   Defines a declared ACL condition.
 *
 * Responsibility:
 *   Evaluate one contextual authorization condition.
 *
 * Contract:
 *   Conditions are declared objects. No arbitrary Reflection call fallback is allowed.
 *
 * @package ASAP\Acl
 */
#[OpusRefBookClass(
    domain: 'ACL',
    role: 'Define the callable boundary for ACL conditions',
    responsibility: 'Constrain conditional authorization checks to declared objects receiving an explicit AccessContext.',
    contracts: [
        'Conditions do not select ACL rules.',
        'Conditions receive only the explicit AccessContext.',
        'Condition failures must be explicit and must not be ignored silently.',
    ],
    examples: ['acl-condition'],
    diagrams: ['acl-runtime'],
    introducedIn: 'P112Q3E2'
)]
interface AccessConditionInterface
{
    /**
     * PUBLIC API
     *
     * @param AccessContext $context Validated access context.
     *
     * @return bool True when the condition passes.
     *
     * @throws AccessControlException When the condition cannot be evaluated.
     */
    #[OpusRefBookMethod(
        role: 'Evaluate one declared ACL condition',
        behavior: 'Checks explicit AccessContext values to decide whether a conditional ACL rule may be applied.',
        preconditions: ['The context argument is an explicit AccessContext instance.'],
        postconditions: ['The returned boolean declares whether the condition passes.'],
        sideEffects: ['none'],
        errors: ['ACL_CONDITION_FAILED', 'ACL_CONTEXT_INVALID'],
        testRefs: ['tests/Contract/RefBookAclMetadataContractTest.php'],
        examples: ['acl-condition'],
        diagrams: ['acl-runtime'],
        introducedIn: 'P112Q3E2'
    )]
    public function allows(AccessContext $context): bool;
}
