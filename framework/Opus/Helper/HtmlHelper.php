<?php

declare(strict_types=1);

namespace Opus\Helper;

/*
 * OPUS_REFBOOK:
 *   domain: HELPER
 *   role: Class HtmlHelper belongs to the HELPER Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the HELPER domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - helper-overview
 *   diagrams:
 *     - helper-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC HELPER
 *
 * Role:
 *   Provide small HTML escaping helpers.
 *
 * Contract:
 *   Representation helper only. No routing, no controller, no I18N lookup.
 *
 * Since:
 *   P112D4B
 */
final class HtmlHelper
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string,string|int|float|bool> $attributes
     */
    public static function attributes(array $attributes): string
    {
        $chunks = [];

        foreach ($attributes as $name => $value) {
            if ($value === false) {
                continue;
            }

            if ($value === true) {
                $chunks[] = self::escape((string) $name);
                continue;
            }

            $chunks[] = self::escape((string) $name) . '="' . self::escape((string) $value) . '"';
        }

        return implode(' ', $chunks);
    }
}
