<?php

declare(strict_types=1);

namespace Opus\Asset;

use ASAP\Contract\ContractException;

/*
 * OPUS_REFBOOK:
 *   domain: ASSET
 *   role: Class AssetDefinition belongs to the ASSET Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the ASSET domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - asset-overview
 *   diagrams:
 *     - asset-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Declare one public asset.
 *
 * Contract:
 *   Asset declaration is data only. No rendering side effect.
 *
 * Since:
 *   P112D4A
 */
final class AssetDefinition
{
    public function __construct(
        public readonly string $type,
        public readonly string $path
    ) {
        if (!in_array($this->type, ['css', 'js'], true)) {
            throw ContractException::because('OPUS_ASSET_TYPE_INVALID', $this->type);
        }

        if (trim($this->path) === '') {
            throw ContractException::because('OPUS_ASSET_PATH_EMPTY');
        }
    }
}
