<?php

declare(strict_types=1);

namespace Opus\Documentation;

/**
 * Immutable description of a real OPUS runtime symbol discovered from source and Reflection.
 */
final class RuntimeClassInfo
{
    /**
     * @param list<RuntimeMethodInfo> $publicMethods
     * @param list<string> $interfaces
     * @param list<string> $traits
     * @param list<string> $attributes
     * @param list<string> $diagnostics
     */
    public function __construct(
        private readonly string $name,
        private readonly string $namespace,
        private readonly string $shortName,
        private readonly string $domain,
        private readonly string $type,
        private readonly string $file,
        private readonly int $mtime,
        private readonly ?string $parentClass,
        private readonly array $interfaces,
        private readonly array $traits,
        private readonly array $attributes,
        private readonly ?string $docComment,
        private readonly array $publicMethods,
        private readonly array $diagnostics = []
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function shortName(): string
    {
        return $this->shortName;
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

    public function mtime(): int
    {
        return $this->mtime;
    }

    public function parentClass(): ?string
    {
        return $this->parentClass;
    }

    /** @return list<string> */
    public function interfaces(): array
    {
        return $this->interfaces;
    }

    /** @return list<string> */
    public function traits(): array
    {
        return $this->traits;
    }

    /** @return list<string> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function docComment(): ?string
    {
        return $this->docComment;
    }

    /** @return list<RuntimeMethodInfo> */
    public function publicMethods(): array
    {
        return $this->publicMethods;
    }

    /** @return list<string> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
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
