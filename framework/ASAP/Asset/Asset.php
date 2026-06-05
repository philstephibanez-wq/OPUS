<?php

declare(strict_types=1);

namespace ASAP\Asset;

use ASAP\Contract\ContractException;

/**
 * PUBLIC LEGACY-COLLISION RECONCILIATION
 *
 * Role:
 *   Preserve the ASAP ASSET concept inside the canonical Windows-safe
 *   `ASAP\Asset` namespace/directory.
 *
 * Responsibility:
 *   Carry one public asset reference.
 *
 * Contract:
 *   Asset is declaration data only. Rendering belongs to VIEW/TEMPLATE.
 *
 * Since:
 *   P112D4E
 *
 * Deepened:
 *   P112D4F
 */
final class Asset
{
    public function __construct(
        public readonly string $type,
        public readonly string $href,
        public readonly string $media = ''
    ) {
        if (!in_array($this->type, ['css', 'js', 'image'], true)) {
            throw ContractException::because('ASAP_ASSET_TYPE_INVALID', $this->type);
        }

        if (trim($this->href) === '') {
            throw ContractException::because('ASAP_ASSET_HREF_EMPTY');
        }
    }

    public static function css(string $href, string $media = 'all'): self
    {
        return new self('css', $href, $media);
    }

    public static function js(string $href): self
    {
        return new self('js', $href);
    }

    public static function image(string $href): self
    {
        return new self('image', $href);
    }

    public function isCss(): bool
    {
        return $this->type === 'css';
    }

    public function isJs(): bool
    {
        return $this->type === 'js';
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }
}
