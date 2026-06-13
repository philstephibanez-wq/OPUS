<?php

declare(strict_types=1);

namespace Opus\Routing;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;
use RuntimeException;
use Throwable;

/*
 * OPUS_REFBOOK:
 *   domain: ROUTING
 *   role: Class RouteCompilerException belongs to the ROUTING Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the ROUTING domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - routing-overview
 *   diagrams:
 *     - routing-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Report route compilation/manifest errors explicitly.
 *
 * Responsibility:
 *   Keep route compilation failures deterministic and easy to test.
 *
 * Contract:
 *   No silent fallback. A duplicate name, duplicate path/method, missing class
 *   or invalid manifest is always a blocking exception.
 *
 * Since:
 *   P112Q1
 */
#[OpusRefBookClass(
    domain: 'ROUTING',
    role: 'Represent explicit routing compilation failures',
    responsibility: 'Carry deterministic route compiler error codes for invalid route attributes, indexes and manifests.',
    contracts: [
        'Routing compilation errors must never be downgraded to fallback routing.',
        'Every invalid route boundary exposes a stable diagnostic code.',
    ],
    examples: ['routing-overview', 'attribute-routing'],
    diagrams: ['routing-runtime'],
    introducedIn: 'P112Q3E3'
)]
final class RouteCompilerException extends RuntimeException implements RefBookInspectableInterface
{
    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for route compiler exceptions',
        behavior: 'Returns the stable ROUTING domain used by RefBook scanners and snapshot consumers.',
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

    #[OpusRefBookMethod(
        role: 'Create an explicit route compiler exception',
        behavior: 'Builds a RouteCompilerException with a stable code and optional detail while preserving an optional previous exception.',
        preconditions: ['A stable routing error code is provided.'],
        postconditions: ['Returns a RouteCompilerException carrying the explicit route compiler failure.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['routing-overview'],
        diagrams: ['routing-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public static function because(string $code, string $detail = '', ?Throwable $previous = null): self
    {
        $message = $detail === '' ? $code : $code . ': ' . $detail;

        return new self($message, 0, $previous);
    }
}
