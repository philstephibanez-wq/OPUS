<?php

declare(strict_types=1);

namespace Opus\RefBook\Model;

/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Class RefBookScanResult belongs to the REFBOOK Opus framework domain.
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
 * PUBLIC immutable RefBook scan result.
 *
 * Role:
 *   Groups scanned classes and summary counters for validators, snapshot
 *   builders and future OPUS_REF_BOOK API endpoints.
 */
final class RefBookScanResult
{
    /** @var array<int,RefBookClassEntry> */
    private array $classes;

    /** @var array<int,string> */
    private array $loadErrors;

    /** @param array<int,RefBookClassEntry> $classes */
    public function __construct(array $classes, array $loadErrors = [])
    {
        $this->classes = $classes;
        $this->loadErrors = $loadErrors;
    }

    /**
     * PUBLIC class list accessor.
     *
     * @return array<int,RefBookClassEntry>
     */
    public function classes(): array
    {
        return $this->classes;
    }

    /**
     * PUBLIC load-error accessor.
     *
     * @return array<int,string>
     */
    public function loadErrors(): array
    {
        return $this->loadErrors;
    }

    /**
     * PUBLIC summary builder.
     *
     * @return array<string,int>
     */
    public function summary(): array
    {
        $publicMethods = 0;
        $classMetadataMissing = 0;
        $methodMetadataMissing = 0;
        foreach ($this->classes as $class) {
            if (!$class->hasMetadata()) {
                $classMetadataMissing++;
            }
            foreach ($class->methods() as $method) {
                $publicMethods++;
                if (!$method->hasMetadata()) {
                    $methodMetadataMissing++;
                }
            }
        }

        return [
            'classes' => count($this->classes),
            'public_methods' => $publicMethods,
            'class_metadata_missing' => $classMetadataMissing,
            'method_metadata_missing' => $methodMetadataMissing,
            'load_errors' => count($this->loadErrors),
        ];
    }

    /**
     * PUBLIC snapshot exporter.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary(),
            'load_errors' => $this->loadErrors,
            'classes' => array_map(static function (RefBookClassEntry $class): array {
                return $class->toArray();
            }, $this->classes),
        ];
    }
}
