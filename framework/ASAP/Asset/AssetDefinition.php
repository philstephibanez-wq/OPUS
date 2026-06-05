<?php

declare(strict_types=1);

namespace ASAP\Asset;

use ASAP\Contract\ContractException;

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
            throw ContractException::because('ASAP_ASSET_TYPE_INVALID', $this->type);
        }

        if (trim($this->path) === '') {
            throw ContractException::because('ASAP_ASSET_PATH_EMPTY');
        }
    }
}
