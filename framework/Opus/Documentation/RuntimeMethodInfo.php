<?php

declare(strict_types=1);

namespace Opus\Documentation;

/**
 * Immutable description of a reflected public method.
 */
final class RuntimeMethodInfo
{
    private string $name;
    private bool $isStatic;
    private string $declaringClass;
    private ?string $returnType;
    /** @var list<array{name:string,type:string|null,optional:bool,default:mixed}> */
    private array $parameters;
    private ?string $docComment;
    private int $startLine;
    private int $endLine;
    /** @var list<string> */
    private array $attributes;

    /**
     * @param list<array{name:string,type:string|null,optional:bool,default:mixed}> $parameters
     * @param list<string> $attributes
     */
    public function __construct(
        string $name,
        bool $isStatic,
        string $declaringClass,
        ?string $returnType,
        array $parameters,
        ?string $docComment,
        int $startLine,
        int $endLine,
        array $attributes
    ) {
        $this->name = $name;
        $this->isStatic = $isStatic;
        $this->declaringClass = $declaringClass;
        $this->returnType = $returnType;
        $this->parameters = $parameters;
        $this->docComment = $docComment;
        $this->startLine = $startLine;
        $this->endLine = $endLine;
        $this->attributes = $attributes;
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
