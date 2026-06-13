<?php

declare(strict_types=1);

namespace Opus\Theme;

use ASAP\Contract\ContractException;

/*
 * OPUS_REFBOOK:
 *   domain: THEME
 *   role: Class ThemeDefinition belongs to the THEME Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the THEME domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - theme-overview
 *   diagrams:
 *     - theme-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Declare one Opus theme.
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
            throw ContractException::because('OPUS_THEME_ID_EMPTY');
        }
    }
}
