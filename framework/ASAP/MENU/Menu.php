<?php

declare(strict_types=1);

namespace ASAP\MENU;

/**
 * PUBLIC LEGACY-ALIGNED MENU
 *
 * Role:
 *   Preserve the original ASAP `MENU\Menu` concept.
 *
 * Responsibility:
 *   Carry ordered navigation entries.
 *
 * Contract:
 *   Menu carries navigation data only. Templates/renderers display it.
 *
 * Since:
 *   P112D4C
 */
final class Menu
{
    /** @var array<int,array{label:string,href:string,active:bool}> */
    private array $items = [];

    public function add(string $label, string $href, bool $active = false): void
    {
        if (trim($label) === '' || trim($href) === '') {
            throw new \InvalidArgumentException('ASAP_MENU_ITEM_INVALID');
        }

        $this->items[] = ['label' => $label, 'href' => $href, 'active' => $active];
    }

    /**
     * @return array<int,array{label:string,href:string,active:bool}>
     */
    public function items(): array
    {
        return $this->items;
    }
}
