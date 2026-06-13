<?php

declare(strict_types=1);

namespace Opus\Compatibility;

use SimpleXMLElement;

/*
 * OPUS_REFBOOK:
 *   domain: COMPATIBILITY
 *   role: Class SimpleXMLElementExtended belongs to the COMPATIBILITY Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the COMPATIBILITY domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - compatibility-overview
 *   diagrams:
 *     - compatibility-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the old Opus XML convenience API over SimpleXMLElement.
 *
 * Contract:
 *   Read-only helpers. Missing attributes return null explicitly.
 *
 * Since:
 *   P112O
 */
class SimpleXMLElementExtended extends SimpleXMLElement
{
    public function getAttribute(string $name): ?string
    {
        $attributes = $this->attributes();

        if ($attributes === null || !isset($attributes[$name])) {
            return null;
        }

        return (string) $attributes[$name];
    }

    public function getAttributeCount(): int
    {
        return count($this->getAttributesArray());
    }

    /**
     * @return string[]
     */
    public function getAttributeNames(): array
    {
        return array_keys($this->getAttributesArray());
    }

    /**
     * @return array<string,string>
     */
    public function getAttributesArray(): array
    {
        $out = [];
        $attributes = $this->attributes();

        if ($attributes === null) {
            return $out;
        }

        foreach ($attributes as $name => $value) {
            $out[(string) $name] = (string) $value;
        }

        return $out;
    }

    public function getChildrenCount(): int
    {
        return count($this->children());
    }
}
