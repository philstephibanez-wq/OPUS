<?php

declare(strict_types=1);

namespace ASAP\Asset;

/**
 * PUBLIC REGISTRY
 *
 * Role:
 *   Store public CSS/JS assets in declaration order.
 *
 * Responsibility:
 *   Provide filtered asset lists for renderers.
 *
 * Contract:
 *   Registry stores representation metadata only.
 *
 * Since:
 *   P112D4A
 */
final class AssetRegistry
{
    /** @var AssetDefinition[] */
    private array $assets = [];

    /**
     * PUBLIC API
     *
     * @param AssetDefinition $asset Asset declaration.
     *
     * @return void
     */
    public function add(AssetDefinition $asset): void
    {
        $this->assets[] = $asset;
    }

    /**
     * PUBLIC API
     *
     * @param string $type css or js.
     *
     * @return AssetDefinition[] Assets of the requested type.
     */
    public function byType(string $type): array
    {
        return array_values(array_filter(
            $this->assets,
            static fn (AssetDefinition $asset): bool => $asset->type === $type
        ));
    }
}
