<?php

declare(strict_types=1);

namespace ASAP\Routing;

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
final class ClassIndex
{
    /** @var array<string,string|null> */
    private array $classes;

    /**
     * @param array<int|string,string|null> $classes Either class names or class => path map.
     */
    public function __construct(array $classes)
    {
        $normalized = [];

        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                if (!is_string($value) || trim($value) === '') {
                    throw RouteCompilerException::because('ASAP_CLASS_INDEX_CLASS_INVALID');
                }

                $normalized[$value] = null;
                continue;
            }

            if (!is_string($key) || trim($key) === '') {
                throw RouteCompilerException::because('ASAP_CLASS_INDEX_CLASS_INVALID');
            }

            if ($value !== null && (!is_string($value) || trim($value) === '')) {
                throw RouteCompilerException::because('ASAP_CLASS_INDEX_PATH_INVALID', $key);
            }

            $normalized[$key] = $value;
        }

        ksort($normalized);
        $this->classes = $normalized;
    }

    /**
     * PUBLIC FACTORY
     *
     * Composer classmaps contain class => path entries. ASAP can pass that
     * classmap here without making Composer responsible for route compilation.
     *
     * @param array<string,string> $classMap
     */
    public static function fromComposerClassMap(array $classMap): self
    {
        return new self($classMap);
    }

    /** @return string[] */
    public function classes(): array
    {
        return array_keys($this->classes);
    }

    public function pathForClass(string $class): ?string
    {
        return $this->classes[$class] ?? null;
    }

    /** @return string[] */
    public function classesInNamespace(string $namespace): array
    {
        $namespace = trim($namespace, '\\');

        if ($namespace === '') {
            throw RouteCompilerException::because('ASAP_CLASS_INDEX_NAMESPACE_EMPTY');
        }

        $prefix = $namespace . '\\';

        return array_values(array_filter(
            $this->classes(),
            static fn (string $class): bool => str_starts_with($class, $prefix)
        ));
    }
}
