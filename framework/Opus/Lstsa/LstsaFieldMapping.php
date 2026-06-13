<?php

declare(strict_types=1);

namespace Opus\Lstsa;

use SimpleXMLElement;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaFieldMapping belongs to the LSTSA Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LSTSA domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - lstsa-overview
 *   diagrams:
 *     - lstsa-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC Lstsa FIELD MAPPING
 *
 * Role:
 *   Declare an allowlisted transformation from one source field to one target field.
 */
final class LstsaFieldMapping
{
    /**
     * @param list<string> $transforms
     */
    public function __construct(
        public readonly string $target,
        public readonly string $source,
        public readonly LstsaFieldConstraint $constraint,
        public readonly array $transforms = []
    ) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*$/', $this->target)) {
            throw LstsaException::because('OPUS_Lstsa_TARGET_FIELD_INVALID', $this->target);
        }

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*$/', $this->source)) {
            throw LstsaException::because('OPUS_Lstsa_SOURCE_FIELD_INVALID', $this->source);
        }

        foreach ($this->transforms as $transform) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*$/', $transform)) {
                throw LstsaException::because('OPUS_Lstsa_TRANSFORM_NAME_INVALID', $transform);
            }
        }
    }

    public static function fromXml(SimpleXMLElement $xml): self
    {
        $target = trim((string) ($xml['target'] ?? $xml['name'] ?? ''));
        $source = trim((string) ($xml['source'] ?? $target));
        $transformRaw = trim((string) ($xml['transform'] ?? ''));
        $transforms = $transformRaw === '' ? [] : array_values(array_filter(array_map('trim', preg_split('/[|,]/', $transformRaw) ?: [])));

        return new self(
            $target,
            $source,
            LstsaFieldConstraint::fromXml($xml, 'target'),
            $transforms
        );
    }
}
