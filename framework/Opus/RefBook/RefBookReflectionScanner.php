<?php

declare(strict_types=1);

namespace Opus\RefBook;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;
use ASAP\RefBook\Model\RefBookClassEntry;
use ASAP\RefBook\Model\RefBookMethodEntry;
use ASAP\RefBook\Model\RefBookScanResult;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Class RefBookReflectionScanner belongs to the REFBOOK Opus framework domain.
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
/**
 * PUBLIC RefBook Reflection scanner.
 *
 * Role:
 *   Scans PHP classes with Reflection to extract the technical truth required by
 *   OPUS_REF_BOOK generators, snapshots and future API endpoints.
 *
 * Contract:
 *   - never guesses signatures;
 *   - never mutates source files;
 *   - never treats class-level metadata as method-level metadata;
 *   - reports load errors explicitly;
 *   - returns a typed scan result consumed by validators and snapshot builders.
 */
final class RefBookReflectionScanner
{
    /** @var array<string,string> */
    private array $autoloadRoots = [];

    /**
     * PUBLIC scanner entrypoint.
     *
     * @param string $sourceRoot Directory containing PHP sources to inspect.
     * @param string $namespacePrefix Optional PSR-4 namespace prefix for autoloading.
     *
     * @return RefBookScanResult Reflection-backed scan result.
     */
    public function scan(string $sourceRoot, string $namespacePrefix = ''): RefBookScanResult
    {
        $sourceRoot = rtrim($sourceRoot, DIRECTORY_SEPARATOR);
        if (!is_dir($sourceRoot)) {
            throw new \InvalidArgumentException('OPUS_REFBOOK_SOURCE_ROOT_MISSING: ' . $sourceRoot);
        }

        if ($namespacePrefix !== '') {
            $this->registerAutoloadRoot($namespacePrefix, $sourceRoot);
        }

        $files = $this->listPhpFiles($sourceRoot);
        $symbolsByFile = [];
        foreach ($files as $file) {
            $symbolsByFile[$file] = $this->discoverSymbols($file);
        }

        $loadErrors = [];
        foreach ($files as $file) {
            try {
                require_once $file;
            } catch (Throwable $error) {
                $loadErrors[] = $file . ': ' . $error->getMessage();
            }
        }

        $classes = [];
        foreach ($symbolsByFile as $symbols) {
            foreach ($symbols as $symbol) {
                if (!class_exists($symbol, false) && !interface_exists($symbol, false) && !trait_exists($symbol, false)) {
                    continue;
                }
                $classes[] = $this->buildClassEntry(new ReflectionClass($symbol));
            }
        }

        usort($classes, static function (RefBookClassEntry $left, RefBookClassEntry $right): int {
            return strcmp($left->name(), $right->name());
        });

        return new RefBookScanResult($classes, $loadErrors);
    }

    /**
     * INTERNAL PSR-4 autoload registration for scanned sources.
     */
    private function registerAutoloadRoot(string $namespacePrefix, string $root): void
    {
        $namespacePrefix = trim($namespacePrefix, '\\') . '\\';
        if (isset($this->autoloadRoots[$namespacePrefix])) {
            return;
        }

        $this->autoloadRoots[$namespacePrefix] = $root;
        spl_autoload_register(function (string $class) use ($namespacePrefix, $root): void {
            if (!str_starts_with($class, $namespacePrefix)) {
                return;
            }
            $relative = substr($class, strlen($namespacePrefix));
            $path = $root . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
    }

    /**
     * INTERNAL PHP file listing.
     *
     * @return array<int,string>
     */
    private function listPhpFiles(string $root): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $files = [];
        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }
            if (strtolower($item->getExtension()) !== 'php') {
                continue;
            }
            $files[] = $item->getPathname();
        }
        sort($files);

        return $files;
    }

    /**
     * INTERNAL symbol discovery using tokens without executing the file.
     *
     * @return array<int,string>
     */
    private function discoverSymbols(string $file): array
    {
        $content = file_get_contents($file);
        if (!is_string($content)) {
            throw new \RuntimeException('OPUS_REFBOOK_FILE_READ_FAILED: ' . $file);
        }

        $tokens = token_get_all($content);
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

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                if ($token[0] === T_CLASS && $this->isAnonymousClass($tokens, $i)) {
                    continue;
                }
                $name = $this->readNextString($tokens, $i + 1);
                if ($name !== '') {
                    $symbols[] = ($namespace !== '') ? $namespace . '\\' . $name : $name;
                }
            }
        }

        return $symbols;
    }

    /**
     * INTERNAL namespace parser.
     *
     * @param array<int,mixed> $tokens
     */
    private function readNamespace(array $tokens, int $index): string
    {
        $namespace = '';
        $count = count($tokens);
        for ($i = $index; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === ';' || $token === '{') {
                break;
            }
            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $namespace .= $token[1];
            }
        }

        return trim($namespace, '\\');
    }

    /**
     * INTERNAL anonymous-class guard.
     *
     * @param array<int,mixed> $tokens
     */
    private function isAnonymousClass(array $tokens, int $classIndex): bool
    {
        for ($i = $classIndex - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            $skipTokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_FINAL, T_ABSTRACT];
            if (defined('T_READONLY')) {
                $skipTokens[] = constant('T_READONLY');
            }
            if (is_array($token) && in_array($token[0], $skipTokens, true)) {
                continue;
            }
            return is_array($token) && $token[0] === T_NEW;
        }

        return false;
    }

    /**
     * INTERNAL string parser.
     *
     * @param array<int,mixed> $tokens
     */
    private function readNextString(array $tokens, int $index): string
    {
        $count = count($tokens);
        for ($i = $index; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
            if ($token === '{' || $token === ';') {
                return '';
            }
        }

        return '';
    }

    /**
     * INTERNAL class entry builder.
     */
    private function buildClassEntry(ReflectionClass $class): RefBookClassEntry
    {
        $kind = 'class';
        if ($class->isInterface()) {
            $kind = 'interface';
        } elseif ($class->isTrait()) {
            $kind = 'trait';
        }

        $methods = [];
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            $methods[] = $this->buildMethodEntry($method);
        }
        usort($methods, static function (RefBookMethodEntry $left, RefBookMethodEntry $right): int {
            return strcmp($left->name(), $right->name());
        });

        return new RefBookClassEntry(
            $class->getName(),
            $class->getShortName(),
            $kind,
            (string) $class->getFileName(),
            (int) $class->getStartLine(),
            (int) $class->getEndLine(),
            $class->isFinal(),
            $class->isAbstract(),
            $class->implementsInterface(RefBookInspectableInterface::class),
            $this->firstAttributePayload($class->getAttributes(OpusRefBookClass::class)),
            $methods
        );
    }

    /**
     * INTERNAL method entry builder.
     */
    private function buildMethodEntry(ReflectionMethod $method): RefBookMethodEntry
    {
        return new RefBookMethodEntry(
            $method->getName(),
            $this->methodVisibility($method),
            $method->isStatic(),
            $method->isFinal(),
            $method->isAbstract(),
            array_map(function (ReflectionParameter $parameter): array {
                return $this->parameterPayload($parameter);
            }, $method->getParameters()),
            $this->typeToString($method->getReturnType()),
            $this->firstAttributePayload($method->getAttributes(OpusRefBookMethod::class)),
            (int) $method->getStartLine(),
            (int) $method->getEndLine()
        );
    }

    /**
     * INTERNAL visibility formatter.
     */
    private function methodVisibility(ReflectionMethod $method): string
    {
        if ($method->isPrivate()) {
            return 'private';
        }
        if ($method->isProtected()) {
            return 'protected';
        }

        return 'public';
    }

    /**
     * INTERNAL parameter formatter.
     *
     * @return array<string,mixed>
     */
    private function parameterPayload(ReflectionParameter $parameter): array
    {
        $payload = [
            'name' => $parameter->getName(),
            'type' => $this->typeToString($parameter->getType()),
            'allows_null' => $parameter->allowsNull(),
            'is_optional' => $parameter->isOptional(),
            'is_variadic' => $parameter->isVariadic(),
            'is_by_reference' => $parameter->isPassedByReference(),
            'has_default' => $parameter->isDefaultValueAvailable(),
        ];

        if ($parameter->isDefaultValueAvailable()) {
            $payload['default'] = $parameter->isDefaultValueConstant()
                ? $parameter->getDefaultValueConstantName()
                : $parameter->getDefaultValue();
        }

        return $payload;
    }

    /**
     * INTERNAL Reflection type formatter.
     */
    private function typeToString(?ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof ReflectionNamedType) {
            return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
        }
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(function (ReflectionNamedType $named): string {
                return $named->getName();
            }, $type->getTypes()));
        }

        return (string) $type;
    }

    /**
     * INTERNAL attribute payload extractor.
     *
     * @param array<int,ReflectionAttribute> $attributes
     * @return array<string,mixed>|null
     */
    private function firstAttributePayload(array $attributes): ?array
    {
        if ($attributes === []) {
            return null;
        }
        $instance = $attributes[0]->newInstance();
        if (method_exists($instance, 'toArray')) {
            return $instance->toArray();
        }

        return null;
    }
}
