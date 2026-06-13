<?php

declare(strict_types=1);

namespace Opus\I18n\Plural;

use ASAP\I18n\PluralRuleInterface;

/*
 * OPUS_REFBOOK:
 *   domain: I18N
 *   role: Class RussianPluralRule belongs to the I18N Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the I18N domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - i18n-overview
 *   diagrams:
 *     - i18n-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC PLURAL RULE
 *
 * Role:
 *   Select Russian plural categories.
 *
 * Responsibility:
 *   Preserve the legacy Opus ability to support complex plural languages.
 *
 * Contract:
 *   Russian categories are `one`, `few` and `many`.
 *
 * Since:
 *   P112D4A
 */
final class RussianPluralRule implements PluralRuleInterface
{
    public function select(int $count): string
    {
        $n = abs($count);
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 'one';
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 'few';
        }

        return 'many';
    }
}
