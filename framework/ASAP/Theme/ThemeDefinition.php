<?php

declare(strict_types=1);

namespace ASAP\Theme;

use ASAP\Contract\ContractException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Declare one ASAP theme.
 *
 * Contract:
 *   Theme declaration is representation data only.
 *
 * Since:
 *   P112D4A
 */
final class ThemeDefinition
{
    /**
     * @param string[] $cssFiles CSS assets.
     * @param string[] $jsFiles JavaScript assets.
     */
    public function __construct(
        public readonly string $id,
        public readonly array $cssFiles,
        public readonly array $jsFiles
    ) {
        if (trim($this->id) === '') {
            throw ContractException::because('ASAP_THEME_ID_EMPTY');
        }
    }
}
