<?php

declare(strict_types=1);

namespace ASAP\Menu;

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
            throw MenuException::because('ASAP_MENU_ITEMS_EMPTY');
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
