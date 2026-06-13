<?php

declare(strict_types=1);

namespace Opus\Documentation;

/**
 * Immutable description of a reflected public method.
 */
final class RuntimeMethodInfo
{
    /**
     * @param list<array{name:string,type:string|null,optional:bool,default:mixed}> $parameters
     * @param list<string> $attributes
     */
    public function __construct(
        private readonly string $name,
        private readonly bool $isStatic,
        private readonly string $declaringClass,
        private readonly ?string $returnType,
        private readonly array $parameters,
        private readonly ?string $docComment,
        private readonly int $startLine,
        private readonly int $endLine,
        private readonly array $attributes
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'visibility' => 'public',
            'static' => $this->isStatic,
            'declaring_class' => $this->declaringClass,
            'return_type' => $this->returnType,
            'parameters' => $this->parameters,
            'doc_comment' => $this->docComment,
            'start_line' => $this->startLine,
            'end_line' => $this->endLine,
            'attributes' => $this->attributes,
        ];
    }
}
