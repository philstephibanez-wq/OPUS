<?php

declare(strict_types=1);

namespace Opus\Menu;

/*
 * OPUS_REFBOOK:
 *   domain: MENU
 *   role: Class MenuItem belongs to the MENU Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the MENU domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - menu-overview
 *   diagrams:
 *     - menu-runtime
 * END_OPUS_REFBOOK
 */
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
 implements MenuItemInterface {
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
            throw MenuException::because('OPUS_MENU_LABEL_EMPTY');
        }

        if (trim($this->href) === '') {
            throw MenuException::because('OPUS_MENU_HREF_EMPTY');
        }
    }
}
