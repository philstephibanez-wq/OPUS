<?php

declare(strict_types=1);

namespace Opus\Menu;

/*
 * OPUS_REFBOOK:
 *   domain: MENU
 *   role: Class MenuTree belongs to the MENU Opus framework domain.
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
 *   Carry a menu tree.
 *
 * Contract:
 *   Data only. No template rendering.
 *
 * Since:
 *   P112D4B
 */
final class MenuTree
{
    /**
     * @param MenuItem[] $items Menu items.
     */
    public function __construct(public readonly array $items)
    {
        if ($this->items === []) {
            throw MenuException::because('OPUS_MENU_ITEMS_EMPTY');
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function toArray(): array
    {
        return array_map([$this, 'itemToArray'], $this->items);
    }

    /**
     * @return array<string,mixed>
     */
    private function itemToArray(MenuItem $item): array
    {
        return [
            'label' => $item->label,
            'href' => $item->href,
            'active' => $item->active,
            'children' => array_map([$this, 'itemToArray'], $item->children),
        ];
    }
}
