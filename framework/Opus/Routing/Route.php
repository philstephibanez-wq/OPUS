<?php

declare(strict_types=1);

namespace Opus\Routing;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;
use Attribute;

/**
 * PUBLIC ATTRIBUTE
 *
 * Role:
 *   Declare one explicit Opus route directly on a controller method.
 *
 * Responsibility:
 *   Carry route metadata only. It does not register itself, scan files,
 *   compile manifests, authorize users or dispatch controllers.
 *
 * Contract:
 *   The route compiler may read this attribute through Reflection, but route
 *   compilation remains an explicit action. No compilation is triggered during
 *   PHP autoload.
 *
 * Example:
 *   #[Route(path: '/kb/search', name: 'kb.search', methods: ['GET'], acl: 'kb.read')]
 *   public function index(): Response
 *
 * Since:
 *   P112Q1
 */
/*
 * OPUS_REFBOOK:
 *   domain: ROUTING
 *   role: Class Route belongs to the ROUTING Opus framework domain.
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
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
#[OpusRefBookClass(
    domain: 'ROUTING',
    role: 'Declare explicit route metadata on controller methods',
    responsibility: 'Carry route metadata for explicit compilation without registering, authorizing or dispatching routes automatically.',
    contracts: [
        'Route attributes are data-only declarations.',
        'Compilation is explicit and never triggered during autoload.',
        'Path, name, methods and format must be valid before a route can be compiled.',
    ],
    examples: ['routing-overview', 'attribute-routing'],
    diagrams: ['routing-runtime', 'secure-dispatch-runtime'],
    introducedIn: 'P112Q3E3'
)]
final class Route implements RefBookInspectableInterface
{
    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for route attributes',
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
     * @param string[] $methods HTTP methods accepted by the route.
     */
    #[OpusRefBookMethod(
        role: 'Create a route attribute declaration',
        behavior: 'Stores controller method route metadata after validating the explicit path, name, method list and format.',
        preconditions: ['Path must start with /.', 'Name must not be empty.', 'At least one HTTP method must be declared.', 'Format must not be empty.'],
        postconditions: ['The route attribute is ready for explicit RouteDefinition compilation.'],
        sideEffects: ['none'],
        errors: ['OPUS_ROUTE_ATTRIBUTE_PATH_INVALID', 'OPUS_ROUTE_ATTRIBUTE_NAME_EMPTY', 'OPUS_ROUTE_ATTRIBUTE_METHODS_EMPTY', 'OPUS_ROUTE_ATTRIBUTE_METHOD_INVALID', 'OPUS_ROUTE_ATTRIBUTE_FORMAT_EMPTY'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['attribute-routing'],
        diagrams: ['routing-runtime', 'secure-dispatch-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public function __construct(
        public readonly string $path,
        public readonly string $name,
        public readonly array $methods = ['GET'],
        public readonly ?string $host = null,
        public readonly ?string $locale = null,
        public readonly string $format = 'html',
        public readonly ?string $acl = null,
        public readonly ?string $fsmGuard = null,
        public readonly int $priority = 0
    ) {
        if (trim($this->path) === '' || !str_starts_with($this->path, '/')) {
            throw RouteCompilerException::because('OPUS_ROUTE_ATTRIBUTE_PATH_INVALID', $this->path);
        }

        if (trim($this->name) === '') {
            throw RouteCompilerException::because('OPUS_ROUTE_ATTRIBUTE_NAME_EMPTY');
        }

        if ($this->methods === []) {
            throw RouteCompilerException::because('OPUS_ROUTE_ATTRIBUTE_METHODS_EMPTY', $this->name);
        }

        foreach ($this->methods as $method) {
            if (!is_string($method) || trim($method) === '') {
                throw RouteCompilerException::because('OPUS_ROUTE_ATTRIBUTE_METHOD_INVALID', $this->name);
            }
        }

        if (trim($this->format) === '') {
            throw RouteCompilerException::because('OPUS_ROUTE_ATTRIBUTE_FORMAT_EMPTY', $this->name);
        }
    }

    /** @return string[] */
    #[OpusRefBookMethod(
        role: 'Return normalized route attribute HTTP methods',
        behavior: 'Returns unique uppercase HTTP methods sorted deterministically from the attribute method list.',
        preconditions: ['The route attribute has been constructed successfully.'],
        postconditions: ['Returns unique uppercase sorted HTTP methods.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['attribute-routing'],
        diagrams: ['routing-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public function normalizedMethods(): array
    {
        $methods = array_values(array_unique(array_map(
            static fn (string $method): string => strtoupper(trim($method)),
            $this->methods
        )));
        sort($methods);

        return $methods;
    }
}
