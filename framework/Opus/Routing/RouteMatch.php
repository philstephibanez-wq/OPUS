<?php

declare(strict_types=1);

namespace Opus\Routing;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/*
 * OPUS_REFBOOK:
 *   domain: ROUTING
 *   role: Class RouteMatch belongs to the ROUTING Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the ROUTING domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - routing-overview
 *     - secure-dispatch-gate
 *   diagrams:
 *     - routing-runtime
 *     - secure-dispatch-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry the result of a successful route match.
 *
 * Responsibility:
 *   Provide controller class, action, route parameters and route-level security
 *   metadata to the secure dispatch gate and controller dispatcher.
 *
 * Contract:
 *   Data only. No dispatch, no rendering, no authorization decision.
 *
 * Since:
 *   P112D1
 *
 * Extended:
 *   P112Q3B carries route-aware ACL and FSM metadata.
 *   P112Q3E3 exposes ROUTING functional metadata through RefBook attributes.
 */
#[OpusRefBookClass(
    domain: 'ROUTING',
    role: 'Carry one successful route match',
    responsibility: 'Transport controller target, action, route params and route-level security metadata after matching succeeds.',
    contracts: [
        'RouteMatch is immutable data only.',
        'RouteMatch must not dispatch controllers, authorize access or render output.',
        'Route-level ACL and FSM metadata must remain explicit and inspectable.',
    ],
    examples: ['routing-overview', 'secure-dispatch-gate'],
    diagrams: ['routing-runtime', 'secure-dispatch-runtime'],
    introducedIn: 'P112Q3E3'
)]
final class RouteMatch implements RefBookInspectableInterface
{
    /**
     * PUBLIC DTO CONSTRUCTOR
     *
     * @param string $name Matched route name.
     * @param string $controllerClass Explicit controller class.
     * @param string $action Explicit controller action method.
     * @param array<string,string> $params Matched route parameters.
     * @param string|null $acl Optional route ACL override, using `resource:privilege` or `role:resource:privilege`.
     * @param string|null $fsmGuard Optional route FSM signal override.
     */
    #[OpusRefBookMethod(
        role: 'Create one immutable route match result',
        behavior: 'Stores the matched route name, explicit controller target, action, parameters and route-level security metadata.',
        preconditions: ['Router matched an explicit route and validated the HTTP method.'],
        postconditions: ['The match result is ready for secure dispatch without guessing route metadata.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['routing-overview', 'secure-dispatch-gate'],
        diagrams: ['routing-runtime', 'secure-dispatch-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public function __construct(
        public readonly string $name,
        public readonly string $controllerClass,
        public readonly string $action,
        public readonly array $params,
        public readonly ?string $acl = null,
        public readonly ?string $fsmGuard = null
    ) {
    }

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for route matches',
        behavior: 'Returns the stable RefBook domain used by scanners, snapshots and OPUS_REF_BOOK renderers.',
        preconditions: ['none'],
        postconditions: ['The returned domain is ROUTING.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['routing-refbook-domain'],
        diagrams: ['routing-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public static function refBookDomain(): string
    {
        return 'ROUTING';
    }
}
