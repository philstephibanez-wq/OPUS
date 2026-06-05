<?php

declare(strict_types=1);

namespace ASAP\Menu;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Declare one menu entry.
 *
 * Contract:
 *   Menu item is navigation data only. Rendering belongs to templates/renderers.
 *
 * Since:
 *   P112D4B
 */
final class MenuItem
{
    /**
     * @param MenuItem[] $children Child menu entries.
     */
    public function __construct(
        public readonly string $label,
        public readonly string $href,
        public readonly array $children = [],
        public readonly bool $active = false
    ) {
        if (trim($this->label) === '') {
            throw MenuException::because('ASAP_MENU_LABEL_EMPTY');
        }

        if (trim($this->href) === '') {
            throw MenuException::because('ASAP_MENU_HREF_EMPTY');
        }
    }
}
