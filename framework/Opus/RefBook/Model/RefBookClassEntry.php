<?php

declare(strict_types=1);

namespace Opus\RefBook\Model;

/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Class RefBookClassEntry belongs to the REFBOOK Opus framework domain.
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
 * PUBLIC immutable RefBook class scan entry.
 *
 * Role:
 *   Carries Reflection-owned class data plus optional functional metadata
 *   extracted from OpusRefBookClass.
 */
final class RefBookClassEntry
{
    /** @var array<string,mixed>|null */
    private ?array $metadata;

    /** @var array<int,RefBookMethodEntry> */
    private array $methods;

    /** @param array<int,RefBookMethodEntry> $methods */
    public function __construct(
        string $name,
        string $shortName,
        string $kind,
        string $file,
        int $startLine,
        int $endLine,
        bool $isFinal,
        bool $isAbstract,
        bool $implementsInspectable,
        ?array $metadata,
        array $methods
    ) {
        $this->name = $name;
        $this->shortName = $shortName;
        $this->kind = $kind;
        $this->file = $file;
        $this->startLine = $startLine;
        $this->endLine = $endLine;
        $this->isFinal = $isFinal;
        $this->isAbstract = $isAbstract;
        $this->implementsInspectable = $implementsInspectable;
        $this->metadata = $metadata;
        $this->methods = $methods;
    }

    private string $name;
    private string $shortName;
    private string $kind;
    private string $file;
    private int $startLine;
    private int $endLine;
    private bool $isFinal;
    private bool $isAbstract;
    private bool $implementsInspectable;

    /**
     * PUBLIC class name accessor.
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
     * PUBLIC method list accessor.
     *
     * @return array<int,RefBookMethodEntry>
     */
    public function methods(): array
    {
        return $this->methods;
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
            'short_name' => $this->shortName,
            'kind' => $this->kind,
            'file' => $this->file,
            'start_line' => $this->startLine,
            'end_line' => $this->endLine,
            'is_final' => $this->isFinal,
            'is_abstract' => $this->isAbstract,
            'implements_refbook_inspectable' => $this->implementsInspectable,
            'metadata' => $this->metadata,
            'methods' => array_map(static function (RefBookMethodEntry $method): array {
                return $method->toArray();
            }, $this->methods),
        ];
    }
}
