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
 * parses the symbols actually declared by each PHP file and reflects only classes/interfaces/traits/enums that exist now.
 */
final class RuntimeClassCatalog
{
    private const ROOT_NAMESPACE = 'Opus\\';
    private const LEGACY_GLOBAL_PREFIX = 'OPUS_';

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
            $symbols = $this->declaredSymbolsInFile($file);
            if ($symbols === []) {
                $this->diagnostics[] = 'OPUS_REFBOOK_NO_RUNTIME_SYMBOL_DECLARED: ' . $file;
                continue;
            }

            foreach ($symbols as $symbol) {
                if (!$this->symbolExists($symbol)) {
                    $this->loadSourceFile($file);
                }

                if (!$this->symbolExists($symbol)) {
                    $this->diagnostics[] = 'OPUS_REFBOOK_SYMBOL_NOT_LOADABLE: ' . $symbol . ' :: ' . $file;
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($symbol);
                } catch (Throwable $error) {
                    $this->diagnostics[] = 'OPUS_REFBOOK_REFLECTION_FAILED: ' . $symbol . ' :: ' . $error->getMessage();
                    continue;
                }

                $realFile = $reflection->getFileName();
                if ($realFile === false) {
                    $this->diagnostics[] = 'OPUS_REFBOOK_REFLECTION_FILE_MISSING: ' . $symbol;
                    continue;
                }

                $domain = $this->domainFor($reflection, $file);
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

    /** @return list<string> */
    private function declaredSymbolsInFile(string $file): array
    {
        $source = file_get_contents($file);
        if ($source === false) {
            $this->diagnostics[] = 'OPUS_REFBOOK_SOURCE_FILE_UNREADABLE: ' . $file;
            return [];
        }

        $tokens = token_get_all($source);
        $namespace = '';
        $symbols = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $i + 1);
                continue;
            }

            if (!$this->isSymbolDeclarationToken($token[0])) {
                continue;
            }

            if ($token[0] === T_CLASS && $this->isAnonymousClassDeclaration($tokens, $i)) {
                continue;
            }

            $name = $this->readSymbolName($tokens, $i + 1);
            if ($name === null) {
                continue;
            }

            $symbols[] = ($namespace !== '') ? $namespace . '\\' . $name : $name;
        }

        return array_values(array_unique($symbols));
    }

    /** @param list<array|string> $tokens */
    private function readNamespace(array $tokens, int $start): string
    {
        $namespace = '';
        $count = count($tokens);

        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === ';' || $token === '{') {
                break;
            }

            if (is_array($token)) {
                if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $namespace .= $token[1];
                }
                continue;
            }

            if ($token === '\\') {
                $namespace .= $token;
            }
        }

        return trim($namespace, '\\');
    }

    /** @param list<array|string> $tokens */
    private function readSymbolName(array $tokens, int $start): ?string
    {
        $count = count($tokens);

        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_STRING) {
                return $token[1];
            }
            if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return null;
            }
        }

        return null;
    }

    /** @param list<array|string> $tokens */
    private function isAnonymousClassDeclaration(array $tokens, int $position): bool
    {
        for ($i = $position - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) && $token[0] === T_NEW;
        }

        return false;
    }

    private function isSymbolDeclarationToken(int $tokenId): bool
    {
        return $tokenId === T_CLASS
            || $tokenId === T_INTERFACE
            || $tokenId === T_TRAIT
            || (defined('T_ENUM') && $tokenId === constant('T_ENUM'));
    }

    private function loadSourceFile(string $file): void
    {
        try {
            require_once $file;
        } catch (Throwable $error) {
            $this->diagnostics[] = 'OPUS_REFBOOK_SOURCE_FILE_LOAD_FAILED: ' . $file . ' :: ' . $error->getMessage();
        }
    }

    private function symbolExists(string $symbol): bool
    {
        return class_exists($symbol)
            || interface_exists($symbol)
            || trait_exists($symbol)
            || (function_exists('enum_exists') && enum_exists($symbol));
    }

    private function domainFor(ReflectionClass $reflection, string $sourceFile): string
    {
        $namespace = $reflection->getNamespaceName();
        if (str_starts_with($namespace, rtrim(self::ROOT_NAMESPACE, '\\'))) {
            $parts = explode('\\', $namespace);
            $domain = $parts[1] ?? '';
            if ($domain === '') {
                throw new RuntimeClassCatalogException('OPUS_REFBOOK_DOMAIN_UNRESOLVED: ' . $reflection->getName());
            }

            return $this->normalizeDomain($domain);
        }

        if ($namespace === '' && str_starts_with($reflection->getShortName(), self::LEGACY_GLOBAL_PREFIX)) {
            return $this->domainFromPath($sourceFile, $reflection->getName());
        }

        throw new RuntimeClassCatalogException('OPUS_REFBOOK_DOMAIN_UNRESOLVED: ' . $reflection->getName());
    }

    private function domainFromPath(string $sourceFile, string $symbol): string
    {
        $relative = ltrim(substr(str_replace('\\', '/', $sourceFile), strlen($this->sourceRoot)), '/');
        $parts = explode('/', $relative);
        $domain = $parts[0] ?? '';
        if ($domain === '' || str_ends_with($domain, '.php')) {
            throw new RuntimeClassCatalogException('OPUS_REFBOOK_DOMAIN_UNRESOLVED: ' . $symbol);
        }

        return $this->normalizeDomain($domain);
    }

    private function normalizeDomain(string $domain): string
    {
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
