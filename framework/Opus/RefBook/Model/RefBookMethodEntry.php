<?php

declare(strict_types=1);

namespace Opus\RefBook\Model;

/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Class RefBookMethodEntry belongs to the REFBOOK Opus framework domain.
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
 * PUBLIC immutable RefBook method scan entry.
 *
 * Role:
 *   Carries Reflection-owned method signature data plus optional functional
 *   metadata extracted from OpusRefBookMethod.
 */
final class RefBookMethodEntry
{
    /** @var array<int,array<string,mixed>> */
    private array $parameters;

    /** @var array<string,mixed>|null */
    private ?array $metadata;

    public function __construct(
        string $name,
        string $visibility,
        bool $isStatic,
        bool $isFinal,
        bool $isAbstract,
        array $parameters,
        ?string $returnType,
        ?array $metadata,
        int $startLine,
        int $endLine
    ) {
        $this->name = $name;
        $this->visibility = $visibility;
        $this->isStatic = $isStatic;
        $this->isFinal = $isFinal;
        $this->isAbstract = $isAbstract;
        $this->parameters = $parameters;
        $this->returnType = $returnType;
        $this->metadata = $metadata;
        $this->startLine = $startLine;
        $this->endLine = $endLine;
    }

    private string $name;
    private string $visibility;
    private bool $isStatic;
    private bool $isFinal;
    private bool $isAbstract;
    private ?string $returnType;
    private int $startLine;
    private int $endLine;

    /**
     * PUBLIC method name accessor.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * PUBLIC metadata presence accessor.
     */
    public function hasMetadata(): bool
    {
        return is_array($this->metadata);
    }

    /**
     * PUBLIC snapshot exporter.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'visibility' => $this->visibility,
            'is_static' => $this->isStatic,
            'is_final' => $this->isFinal,
            'is_abstract' => $this->isAbstract,
            'parameters' => $this->parameters,
            'return_type' => $this->returnType,
            'metadata' => $this->metadata,
            'start_line' => $this->startLine,
            'end_line' => $this->endLine,
        ];
    }
}
