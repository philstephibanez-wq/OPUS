<?php

declare(strict_types=1);

namespace Opus\Documentation;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * Live OPUS class catalog.
 *
 * This service never reads a persisted symbol index. It scans the current OPUS source tree,
 * resolves PSR-4 class names and reflects only classes/interfaces/traits/enums that exist now.
 */
final class RuntimeClassCatalog
{
    private const ROOT_NAMESPACE = 'Opus\\';

    /** @var list<string> */
    private array $diagnostics = [];

    public function __construct(
        private string $sourceRoot
    ) {
        $this->sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');
    }

    /**
     * @return list<RuntimeClassInfo>
     */
    public function all(): array
    {
        $this->diagnostics = [];
        $classes = [];

        foreach ($this->phpFiles() as $file) {
            $fqcn = $this->fqcnFromFile($file);
            if ($fqcn === null) {
                continue;
            }

            if (!$this->symbolExists($fqcn)) {
                $this->diagnostics[] = 'OPUS_REFBOOK_SYMBOL_NOT_LOADABLE: ' . $fqcn . ' :: ' . $file;
                continue;
            }

            try {
                $reflection = new ReflectionClass($fqcn);
            } catch (Throwable $error) {
                $this->diagnostics[] = 'OPUS_REFBOOK_REFLECTION_FAILED: ' . $fqcn . ' :: ' . $error->getMessage();
                continue;
            }

            $realFile = $reflection->getFileName();
            if ($realFile === false) {
                $this->diagnostics[] = 'OPUS_REFBOOK_REFLECTION_FILE_MISSING: ' . $fqcn;
                continue;
            }

            $domain = $this->domainFor($reflection);
            $classes[] = new RuntimeClassInfo(
                $reflection->getName(),
                $reflection->getNamespaceName(),
                $reflection->getShortName(),
                $domain,
                $this->typeFor($reflection),
                str_replace('\\', '/', $realFile),
                (int) filemtime($realFile),
                ($reflection->getParentClass() !== false) ? $reflection->getParentClass()->getName() : null,
                array_values($reflection->getInterfaceNames()),
                array_values($reflection->getTraitNames()),
                $this->attributeNames($reflection->getAttributes()),
                $this->docComment($reflection->getDocComment()),
                $this->publicMethods($reflection),
                []
            );
        }

        usort($classes, static fn(RuntimeClassInfo $a, RuntimeClassInfo $b): int => strcmp($a->name(), $b->name()));

        return $classes;
    }

    /**
     * @return list<RuntimeClassInfo>
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->all();
        }

        $needle = strtolower($query);

        return array_values(array_filter(
            $this->all(),
            static fn(RuntimeClassInfo $class): bool => str_contains(strtolower($class->name()), $needle)
                || str_contains(strtolower($class->domain()), $needle)
                || str_contains(strtolower($class->type()), $needle)
        ));
    }

    /** @return list<string> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        if (!is_dir($this->sourceRoot)) {
            throw new RuntimeClassCatalogException('OPUS_REFBOOK_SOURCE_ROOT_MISSING: ' . $this->sourceRoot);
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->sourceRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $files[] = str_replace('\\', '/', $file->getPathname());
        }

        sort($files);

        return $files;
    }

    private function fqcnFromFile(string $file): ?string
    {
        $relative = ltrim(substr($file, strlen($this->sourceRoot)), '/');
        if ($relative === '' || !str_ends_with($relative, '.php')) {
            return null;
        }

        $classPart = substr($relative, 0, -4);
        if ($classPart === '') {
            return null;
        }

        return self::ROOT_NAMESPACE . str_replace('/', '\\', $classPart);
    }

    private function symbolExists(string $fqcn): bool
    {
        return class_exists($fqcn)
            || interface_exists($fqcn)
            || trait_exists($fqcn)
            || (function_exists('enum_exists') && enum_exists($fqcn));
    }

    private function domainFor(ReflectionClass $reflection): string
    {
        $namespace = $reflection->getNamespaceName();
        if (!str_starts_with($namespace, rtrim(self::ROOT_NAMESPACE, '\\'))) {
            throw new RuntimeClassCatalogException('OPUS_REFBOOK_DOMAIN_UNRESOLVED: ' . $reflection->getName());
        }

        $parts = explode('\\', $namespace);
        $domain = $parts[1] ?? '';
        if ($domain === '') {
            throw new RuntimeClassCatalogException('OPUS_REFBOOK_DOMAIN_UNRESOLVED: ' . $reflection->getName());
        }

        return match (strtolower($domain)) {
            'lstsa' => 'LSTSA',
            'i18n' => 'I18N',
            'http' => 'HTTP',
            'json' => 'JSON',
            'xml' => 'XML',
            'url' => 'URL',
            default => $domain,
        };
    }

    private function typeFor(ReflectionClass $reflection): string
    {
        if ($reflection->isInterface()) {
            return 'interface';
        }
        if ($reflection->isTrait()) {
            return 'trait';
        }
        if (method_exists($reflection, 'isEnum') && $reflection->isEnum()) {
            return 'enum';
        }

        return 'class';
    }

    /** @param list<ReflectionAttribute<object>> $attributes */
    private function attributeNames(array $attributes): array
    {
        return array_map(static fn(ReflectionAttribute $attribute): string => $attribute->getName(), $attributes);
    }

    private function docComment(string|false $docComment): ?string
    {
        return ($docComment === false) ? null : $docComment;
    }

    /** @return list<RuntimeMethodInfo> */
    private function publicMethods(ReflectionClass $reflection): array
    {
        $methods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methods[] = new RuntimeMethodInfo(
                $method->getName(),
                $method->isStatic(),
                $method->getDeclaringClass()->getName(),
                $this->typeName($method->getReturnType()),
                array_map(fn(ReflectionParameter $parameter): array => $this->parameterInfo($parameter), $method->getParameters()),
                $this->docComment($method->getDocComment()),
                $method->getStartLine() ?: 0,
                $method->getEndLine() ?: 0,
                $this->attributeNames($method->getAttributes())
            );
        }

        return $methods;
    }

    /** @return array{name:string,type:string|null,optional:bool,default:mixed} */
    private function parameterInfo(ReflectionParameter $parameter): array
    {
        return [
            'name' => $parameter->getName(),
            'type' => $this->typeName($parameter->getType()),
            'optional' => $parameter->isOptional(),
            'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
        ];
    }

    private function typeName(?\ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof ReflectionNamedType) {
            return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
        }

        return (string) $type;
    }
}
