<?php

declare(strict_types=1);

namespace Opus\RefBook\Attribute;

use Attribute;

/**
 * PUBLIC RefBook class metadata attribute.
 *
 * Role:
 *   Declares functional documentation metadata that PHP Reflection cannot infer
 *   from the source signature alone.
 *
 * Contract:
 *   - never repeats technical data that Reflection owns;
 *   - describes domain, role, responsibility and functional contract;
 *   - remains machine-readable for OPUS_REF_BOOK snapshot/API generation;
 *   - contains only immutable constructor data.
 */
#[Attribute(Attribute::TARGET_CLASS)]
/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Class OpusRefBookClass belongs to the REFBOOK Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the REFBOOK domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - refbook-overview
 *   diagrams:
 *     - refbook-runtime
 * END_OPUS_REFBOOK
 */
final class OpusRefBookClass
{
    private string $domain;
    private string $role;
    private string $responsibility;
    private string $visibility;

    /** @var array<int,string> */
    private array $contracts;

    /** @var array<int,string> */
    private array $examples;

    /** @var array<int,string> */
    private array $diagrams;
    private string $introducedIn;

    /**
     * PUBLIC metadata constructor.
     *
     * @param string $domain Functional Opus domain, for example FSM, ACL, Router or RefBook.
     * @param string $role Short functional role displayed by the Reference Book.
     * @param string $responsibility Precise business responsibility of the class.
     * @param string $visibility Contractual visibility: public-api, internal or private.
     * @param array<int,string> $contracts Business contract rules and invariants.
     * @param array<int,string> $examples Stable example identifiers consumed by OPUS_REF_BOOK.
     * @param array<int,string> $diagrams Stable diagram identifiers consumed by OPUS_REF_BOOK.
     * @param string $introducedIn Optional delivery or version marker.
     */
    public function __construct(
        string $domain,
        string $role,
        string $responsibility,
        string $visibility = 'public-api',
        array $contracts = [],
        array $examples = [],
        array $diagrams = [],
        string $introducedIn = ''
    ) {
        $this->domain = $domain;
        $this->role = $role;
        $this->responsibility = $responsibility;
        $this->visibility = $visibility;
        $this->contracts = $contracts;
        $this->examples = $examples;
        $this->diagrams = $diagrams;
        $this->introducedIn = $introducedIn;
    }

    /**
     * PUBLIC exporter used by the Reflection scanner.
     *
     * @return array<string,mixed> Machine-readable RefBook class metadata.
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'role' => $this->role,
            'responsibility' => $this->responsibility,
            'visibility' => $this->visibility,
            'contracts' => $this->contracts,
            'examples' => $this->examples,
            'diagrams' => $this->diagrams,
            'introduced_in' => $this->introducedIn,
        ];
    }
}
