<?php

declare(strict_types=1);

namespace ASAP\Routing;

use ReflectionClass;
use ReflectionMethod;

/**
 * PUBLIC PROVIDER
 *
 * Role:
 *   Read PHP 8 route attributes from an explicit class index.
 *
 * Responsibility:
 *   Convert #[Route] method attributes into RouteDefinition objects.
 *
 * Contract:
 *   The provider never guesses controllers. Classes come from ClassIndex, and
 *   each method route becomes one explicit RouteDefinition.
 *
 * Since:
 *   P112Q1
 */
final class AttributeRouteProvider
{
    public function __construct(private readonly ClassIndex $classIndex)
    {
    }

    /** @return RouteDefinition[] */
    public function routes(?string $namespace = null): array
    {
        $classes = $namespace === null
            ? $this->classIndex->classes()
            : $this->classIndex->classesInNamespace($namespace);

        $routes = [];

        foreach ($classes as $className) {
            if (!class_exists($className)) {
                throw RouteCompilerException::because('ASAP_ROUTE_CONTROLLER_CLASS_NOT_FOUND', $className);
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isAbstract()) {
                continue;
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $route = $attribute->newInstance();

                    if (!$route instanceof Route) {
                        throw RouteCompilerException::because('ASAP_ROUTE_ATTRIBUTE_INVALID_INSTANCE', $className . '::' . $method->getName());
                    }

                    $routes[] = new RouteDefinition(
                        $route->name,
                        $route->path,
                        $className,
                        $method->getName(),
                        [],
                        $route->normalizedMethods(),
                        $route->host,
                        $route->locale,
                        $route->format,
                        $route->acl,
                        $route->fsmGuard,
                        $route->priority,
                        'attribute:' . $className . '::' . $method->getName()
                    );
                }
            }
        }

        usort(
            $routes,
            static fn (RouteDefinition $a, RouteDefinition $b): int => $b->priority <=> $a->priority ?: strcmp($a->name, $b->name)
        );

        return $routes;
    }
}
