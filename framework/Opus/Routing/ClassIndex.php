<?php

declare(strict_types=1);

namespace Opus\Routing;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/*
 * OPUS_REFBOOK:
 *   domain: ROUTING
 *   role: Class ClassIndex belongs to the ROUTING Opus framework domain.
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
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent the class map exposed by an autoloader or by an explicit build
 *   step.
 *
 * Responsibility:
 *   Provide route scanners with known classes without forcing them to crawl the
 *   whole filesystem at runtime.
 *
 * Contract:
 *   This class does not autoload or compile anything by itself. It is only a
 *   read-only index.
 *
 * Since:
 *   P112Q1
 */
#[OpusRefBookClass(
    domain: 'ROUTING',
    role: 'Represent an explicit class index for routing scanners',
    responsibility: 'Provide known controller class names and optional source paths to route attribute providers without runtime filesystem crawling.',
    contracts: [
        'Class names must be explicit non-empty strings.',
        'Optional paths must be explicit non-empty strings when present.',
        'The index does not autoload, scan directories or compile routes by itself.',
    ],
    examples: ['routing-overview', 'attribute-routing'],
    diagrams: ['routing-runtime'],
    introducedIn: 'P112Q3E3'
)]
final class ClassIndex implements RefBookInspectableInterface
{
    /** @var array<string,string|null> */
    private array $classes;

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for class indexes',
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

    /**
     * @param array<int|string,string|null> $classes Either class names or class => path map.
     */
    #[OpusRefBookMethod(
        role: 'Create a normalized routing class index',
        behavior: 'Normalizes a list or class-to-path map into a deterministic class index sorted by class name.',
        preconditions: ['Every class key or value must be a non-empty string.', 'Path values must be non-empty strings when provided.'],
        postconditions: ['The class index contains sorted class names with optional paths.'],
        sideEffects: ['none'],
        errors: ['OPUS_CLASS_INDEX_CLASS_INVALID', 'OPUS_CLASS_INDEX_PATH_INVALID'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['attribute-routing'],
        diagrams: ['routing-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public function __construct(array $classes)
    {
        $normalized = [];

        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                if (!is_string($value) || trim($value) === '') {
                    throw RouteCompilerException::because('OPUS_CLASS_INDEX_CLASS_INVALID');
                }

                $normalized[$value] = null;
                continue;
            }

            if (!is_string($key) || trim($key) === '') {
                throw RouteCompilerException::because('OPUS_CLASS_INDEX_CLASS_INVALID');
            }

            if ($value !== null && (!is_string($value) || trim($value) === '')) {
                throw RouteCompilerException::because('OPUS_CLASS_INDEX_PATH_INVALID', $key);
            }

            $normalized[$key] = $value;
        }

        ksort($normalized);
        $this->classes = $normalized;
    }

    /**
     * PUBLIC FACTORY
     *
     * Composer classmaps contain class => path entries. Opus can pass that
     * classmap here without making Composer responsible for route compilation.
     *
     * @param array<string,string> $classMap
     */
    #[OpusRefBookMethod(
        role: 'Build a ClassIndex from a Composer class map',
        behavior: 'Creates a ClassIndex from a Composer-style class-to-path map without assigning routing responsibility to Composer.',
        preconditions: ['The class map contains explicit class names and file paths.'],
        postconditions: ['Returns a normalized ClassIndex instance.'],
        sideEffects: ['none'],
        errors: ['OPUS_CLASS_INDEX_CLASS_INVALID', 'OPUS_CLASS_INDEX_PATH_INVALID'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['attribute-routing'],
        diagrams: ['routing-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public static function fromComposerClassMap(array $classMap): self
    {
        return new self($classMap);
    }

    /** @return string[] */
    #[OpusRefBookMethod(
        role: 'Return indexed class names',
        behavior: 'Returns the deterministic list of class names known by this index.',
        preconditions: ['The ClassIndex has been constructed successfully.'],
        postconditions: ['Returns class names sorted by class name.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['attribute-routing'],
        diagrams: ['routing-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public function classes(): array
    {
        return array_keys($this->classes);
    }

    #[OpusRefBookMethod(
        role: 'Return the optional source path for an indexed class',
        behavior: 'Returns the declared source path for a class when the index knows one, otherwise null.',
        preconditions: ['A class name is provided.'],
        postconditions: ['Returns a path string or null without loading the class.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['attribute-routing'],
        diagrams: ['routing-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public function pathForClass(string $class): ?string
    {
        return $this->classes[$class] ?? null;
    }

    /** @return string[] */
    #[OpusRefBookMethod(
        role: 'Return indexed classes under one namespace',
        behavior: 'Filters the deterministic class list to classes under the requested namespace prefix.',
        preconditions: ['The namespace string must not be empty after trimming separators.'],
        postconditions: ['Returns only indexed classes that start with the namespace prefix.'],
        sideEffects: ['none'],
        errors: ['OPUS_CLASS_INDEX_NAMESPACE_EMPTY'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['attribute-routing'],
        diagrams: ['routing-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public function classesInNamespace(string $namespace): array
    {
        $namespace = trim($namespace, '\\');

        if ($namespace === '') {
            throw RouteCompilerException::because('OPUS_CLASS_INDEX_NAMESPACE_EMPTY');
        }

        $prefix = $namespace . '\\';

        return array_values(array_filter(
            $this->classes(),
            static fn (string $class): bool => str_starts_with($class, $prefix)
        ));
    }
}
