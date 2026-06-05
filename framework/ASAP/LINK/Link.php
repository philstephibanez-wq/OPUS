<?php

declare(strict_types=1);

namespace ASAP\LINK;

/**
 * PUBLIC LEGACY-ALIGNED LINK
 *
 * Role:
 *   Preserve the original ASAP `LINK\Link` object.
 *
 * Responsibility:
 *   Carry one safe link declaration.
 *
 * Contract:
 *   Link is data only. Rendering belongs to templates/views.
 *
 * Since:
 *   P112D4C
 */
final class Link
{
    public function __construct(
        public readonly string $label,
        public readonly string $href
    ) {
        if (trim($this->label) === '' || trim($this->href) === '') {
            throw new \InvalidArgumentException('ASAP_LINK_INVALID');
        }
    }
}
