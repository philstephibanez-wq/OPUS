<?php

declare(strict_types=1);

namespace Opus\Menu;

/*
 * OPUS_REFBOOK:
 *   domain: MENU
 *   role: Class Menu belongs to the MENU Opus framework domain.
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
 * PUBLIC LEGACY-ALIGNED MENU
 *
 * Role:
 *   Preserve the original Opus `MENU\Menu` concept.
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
            throw new \InvalidArgumentException('OPUS_MENU_ITEM_INVALID');
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
