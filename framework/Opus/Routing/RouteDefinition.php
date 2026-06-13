<?php

declare(strict_types=1);

namespace Opus\Routing;

use ASAP\Contract\ContractException;
use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/*
 * OPUS_REFBOOK:
 *   domain: ROUTING
 *   role: Class RouteDefinition belongs to the ROUTING Opus framework domain.
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
 *   Declare one route contract.
 *
 * Responsibility:
 *   Hold route name, pattern, controller class, action, defaults and optional
 *   runtime metadata used by the compiler/manifest pipeline.
 *
 * Contract:
 *   Route target must be explicit. No controller name guessing.
 *
 * Since:
 *   P112D1
 *
 * Extended:
 *   P112Q1 adds methods/host/locale/format/Acl/Fsm/source metadata while
 *   preserving the original constructor arguments.
 *   P112Q3E3 exposes ROUTING functional metadata through RefBook attributes.
 */
#[OpusRefBookClass(
    domain: 'ROUTING',
    role: 'Represent one explicit route declaration',
    responsibility: 'Carry path, controller target, defaults, HTTP methods and route-level metadata used by Router and SecureDispatchGate.',
    contracts: [
        'Route name, path, controller class and action must be explicit non-empty strings.',
        'Route paths must begin with a slash.',
        'HTTP methods must be explicit and normalized before matching.',
        'Route metadata is data only and must not dispatch, authorize or render.',
    ],
    examples: ['routing-overview', 'secure-dispatch-gate'],
    diagrams: ['routing-runtime', 'secure-dispatch-runtime'],
    introducedIn: 'P112Q3E3'
)]
final class RouteDefinition implements RefBookInspectableInterface
{
    /**
     * @param array<string,string> $defaults Route defaults.
     * @param string[] $methods HTTP methods.
     */
    #[OpusRefBookMethod(
        role: 'Create one explicit route definition',
        behavior: 'Stores route identity, target, defaults, HTTP methods and metadata after validating required route invariants.',
        preconditions: ['Route name, path, controller class and action are provided explicitly.'],
        postconditions: ['The route definition is immutable and ready for Router matching.'],
        sideEffects: ['none'],
        errors: ['OPUS_ROUTE_DEFINITION_INVALID', 'OPUS_ROUTE_PATH_INVALID', 'OPUS_ROUTE_METHODS_EMPTY', 'OPUS_ROUTE_METHOD_INVALID', 'OPUS_ROUTE_FORMAT_EMPTY'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['routing-overview', 'secure-dispatch-gate'],
        diagrams: ['routing-runtime', 'secure-dispatch-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $controllerClass,
        public readonly string $action,
        public readonly array $defaults = [],
        public readonly array $methods = ['GET'],
        public readonly ?string $host = null,
        public readonly ?string $locale = null,
        public readonly string $format = 'html',
        public readonly ?string $acl = null,
        public readonly ?string $fsmGuard = null,
        public readonly int $priority = 0,
        public readonly string $source = 'explicit'
    ) {
        foreach ([$this->name, $this->path, $this->controllerClass, $this->action] as $value) {
            if (trim($value) === '') {
                throw ContractException::because('OPUS_ROUTE_DEFINITION_INVALID');
            }
        }

        if (!str_starts_with($this->path, '/')) {
            throw ContractException::because('OPUS_ROUTE_PATH_INVALID', $this->path);
        }

        if ($this->methods === []) {
            throw ContractException::because('OPUS_ROUTE_METHODS_EMPTY', $this->name);
        }

        foreach ($this->normalizedMethods() as $method) {
            if ($method === '') {
                throw ContractException::because('OPUS_ROUTE_METHOD_INVALID', $this->name);
            }
        }

        if (trim($this->format) === '') {
            throw ContractException::because('OPUS_ROUTE_FORMAT_EMPTY', $this->name);
        }
    }

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for route definitions',
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

    /** @return string[] */
    #[OpusRefBookMethod(
        role: 'Return normalized HTTP methods for matching',
        behavior: 'Normalizes configured HTTP method names by trimming, uppercasing, deduplicating and sorting them.',
        preconditions: ['The route definition contains its declared methods.'],
        postconditions: ['The returned methods are stable uppercase values suitable for exact comparison.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['routing-overview'],
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

    /** @return array<string,mixed> */
    #[OpusRefBookMethod(
        role: 'Export the route definition as a manifest row',
        behavior: 'Returns a machine-readable row containing route target, defaults, methods and security metadata for manifest/debug consumers.',
        preconditions: ['The route definition was validated successfully.'],
        postconditions: ['The returned row contains normalized methods and all route metadata fields.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookRoutingMetadataContractTest.php'],
        examples: ['routing-overview', 'secure-dispatch-gate'],
        diagrams: ['routing-runtime', 'secure-dispatch-runtime'],
        introducedIn: 'P112Q3E3'
    )]
    public function toManifestRow(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'methods' => $this->normalizedMethods(),
            'controller' => $this->controllerClass,
            'action' => $this->action,
            'defaults' => $this->defaults,
            'host' => $this->host,
            'locale' => $this->locale,
            'format' => $this->format,
            'acl' => $this->acl,
            'fsm_guard' => $this->fsmGuard,
            'priority' => $this->priority,
            'source' => $this->source,
        ];
    }
}
