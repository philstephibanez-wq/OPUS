<?php

declare(strict_types=1);

namespace ASAP\Routing;

use Attribute;

/**
 * PUBLIC ATTRIBUTE
 *
 * Role:
 *   Declare one explicit ASAP route directly on a controller method.
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
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /**
     * @param string[] $methods HTTP methods accepted by the route.
     */
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
            throw RouteCompilerException::because('ASAP_ROUTE_ATTRIBUTE_PATH_INVALID', $this->path);
        }

        if (trim($this->name) === '') {
            throw RouteCompilerException::because('ASAP_ROUTE_ATTRIBUTE_NAME_EMPTY');
        }

        if ($this->methods === []) {
            throw RouteCompilerException::because('ASAP_ROUTE_ATTRIBUTE_METHODS_EMPTY', $this->name);
        }

        foreach ($this->methods as $method) {
            if (!is_string($method) || trim($method) === '') {
                throw RouteCompilerException::because('ASAP_ROUTE_ATTRIBUTE_METHOD_INVALID', $this->name);
            }
        }

        if (trim($this->format) === '') {
            throw RouteCompilerException::because('ASAP_ROUTE_ATTRIBUTE_FORMAT_EMPTY', $this->name);
        }
    }

    /** @return string[] */
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
