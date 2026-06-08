<?php

declare(strict_types=1);

namespace ASAP\RefBook\Attribute;

use Attribute;

/**
 * PUBLIC RefBook method metadata attribute.
 *
 * Role:
 *   Declares the functional behavior of a public method without duplicating its
 *   technical signature, parameters or return type.
 *
 * Contract:
 *   - Reflection owns method name, parameters and return type;
 *   - this attribute owns role, behavior, preconditions, postconditions, side
 *     effects, errors, tests, examples and diagram links;
 *   - method-level metadata is never inherited silently from class metadata.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class AsapRefBookMethod
{
    private string $role;
    private string $behavior;
    private string $visibility;

    /** @var array<int,string> */
    private array $preconditions;

    /** @var array<int,string> */
    private array $postconditions;

    /** @var array<int,string> */
    private array $sideEffects;

    /** @var array<int,string> */
    private array $errors;

    /** @var array<int,string> */
    private array $testRefs;

    /** @var array<int,string> */
    private array $examples;

    /** @var array<int,string> */
    private array $diagrams;
    private string $introducedIn;

    /**
     * PUBLIC metadata constructor.
     *
     * @param string $role Short functional role displayed by the Reference Book.
     * @param string $behavior Functional behavior in one or two precise sentences.
     * @param string $visibility Contractual visibility: public-api, internal or private.
     * @param array<int,string> $preconditions Required state before calling the method.
     * @param array<int,string> $postconditions Guaranteed state after a successful call.
     * @param array<int,string> $sideEffects Side effects, or ['none'] when none exist.
     * @param array<int,string> $errors Explicit business or contract errors.
     * @param array<int,string> $testRefs Test or recipe references proving the behavior.
     * @param array<int,string> $examples Stable example identifiers consumed by ASAP_REF_BOOK.
     * @param array<int,string> $diagrams Stable diagram identifiers consumed by ASAP_REF_BOOK.
     * @param string $introducedIn Optional delivery or version marker.
     */
    public function __construct(
        string $role,
        string $behavior,
        string $visibility = 'public-api',
        array $preconditions = [],
        array $postconditions = [],
        array $sideEffects = [],
        array $errors = [],
        array $testRefs = [],
        array $examples = [],
        array $diagrams = [],
        string $introducedIn = ''
    ) {
        $this->role = $role;
        $this->behavior = $behavior;
        $this->visibility = $visibility;
        $this->preconditions = $preconditions;
        $this->postconditions = $postconditions;
        $this->sideEffects = $sideEffects;
        $this->errors = $errors;
        $this->testRefs = $testRefs;
        $this->examples = $examples;
        $this->diagrams = $diagrams;
        $this->introducedIn = $introducedIn;
    }

    /**
     * PUBLIC exporter used by the Reflection scanner.
     *
     * @return array<string,mixed> Machine-readable RefBook method metadata.
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'behavior' => $this->behavior,
            'visibility' => $this->visibility,
            'preconditions' => $this->preconditions,
            'postconditions' => $this->postconditions,
            'side_effects' => $this->sideEffects,
            'errors' => $this->errors,
            'test_refs' => $this->testRefs,
            'examples' => $this->examples,
            'diagrams' => $this->diagrams,
            'introduced_in' => $this->introducedIn,
        ];
    }
}
