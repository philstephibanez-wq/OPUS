<?php

declare(strict_types=1);

namespace Opus\Documentation;

/**
 * Immutable description of a real OPUS runtime symbol discovered from source and Reflection.
 */
final class RuntimeClassInfo
{
    private string $name;
    private string $namespace;
    private string $shortName;
    private string $domain;
    private string $type;
    private string $file;
    private int $mtime;
    private ?string $parentClass;
    /** @var list<string> */
    private array $interfaces;
    /** @var list<string> */
    private array $traits;
    /** @var list<string> */
    private array $attributes;
    private ?string $docComment;
    /** @var list<RuntimeMethodInfo> */
    private array $publicMethods;
    /** @var list<string> */
    private array $diagnostics;

    /**
     * @param list<RuntimeMethodInfo> $publicMethods
     * @param list<string> $interfaces
     * @param list<string> $traits
     * @param list<string> $attributes
     * @param list<string> $diagnostics
     */
    public function __construct(
        string $name,
        string $namespace,
        string $shortName,
        string $domain,
        string $type,
        string $file,
        int $mtime,
        ?string $parentClass,
        array $interfaces,
        array $traits,
        array $attributes,
        ?string $docComment,
        array $publicMethods,
        array $diagnostics = []
    ) {
        $this->name = $name;
        $this->namespace = $namespace;
        $this->shortName = $shortName;
        $this->domain = $domain;
        $this->type = $type;
        $this->file = $file;
        $this->mtime = $mtime;
        $this->parentClass = $parentClass;
        $this->interfaces = $interfaces;
        $this->traits = $traits;
        $this->attributes = $attributes;
        $this->docComment = $docComment;
        $this->publicMethods = $publicMethods;
        $this->diagnostics = $diagnostics;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function file(): string
    {
        return $this->file;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'namespace' => $this->namespace,
            'short_name' => $this->shortName,
            'domain' => $this->domain,
            'type' => $this->type,
            'file' => $this->file,
            'mtime' => $this->mtime,
            'parent_class' => $this->parentClass,
            'interfaces' => $this->interfaces,
            'traits' => $this->traits,
            'attributes' => $this->attributes,
            'doc_comment' => $this->docComment,
            'public_methods' => array_map(static fn(RuntimeMethodInfo $method): array => $method->toArray(), $this->publicMethods),
            'diagnostics' => $this->diagnostics,
        ];
    }
}
