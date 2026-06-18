<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Validate and expose the RefBook visual theme contract.
 *
 * Responsibility:
 *   Keep the allowed theme identifiers, legacy explicit aliases, the default
 *   theme and the generated CSS class in one explicit representation service.
 *
 * Contract:
 *   No silent fallback. Missing query theme may be replaced only by the
 *   declared DEFAULT_THEME before construction. Historical theme identifiers
 *   are accepted only through the explicit ALIASES contract and normalize to
 *   a canonical season theme.
 *
 * Version:
 *   P116C5H_SEASONS_THEME_CONTRACT
 */
final class ReferenceThemeService
{
    public const DEFAULT_THEME = 'winter';

    /** @var list<string> */
    public const SUPPORTED_THEMES = ['winter', 'summer', 'spring', 'autumn'];

    /** @var array<string,string> */
    public const ALIASES = [
        'night' => 'winter',
        'ocean' => 'summer',
        'paper' => 'autumn',
    ];

    private readonly string $theme;

    public function __construct(string $theme = self::DEFAULT_THEME)
    {
        $normalizedTheme = self::ALIASES[$theme] ?? $theme;

        if (!in_array($normalizedTheme, self::SUPPORTED_THEMES, true)) {
            throw new RuntimeException('OPUS_REFBOOK_THEME_UNSUPPORTED=' . $theme);
        }

        $this->theme = $normalizedTheme;
    }

    public function theme(): string
    {
        return $this->theme;
    }

    /**
     * @return list<string>
     */
    public function supportedThemes(): array
    {
        return self::SUPPORTED_THEMES;
    }

    public function bodyClass(): string
    {
        return 'theme-' . $this->theme;
    }
}