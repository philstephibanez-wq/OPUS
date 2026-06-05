<?php

declare(strict_types=1);

namespace ASAP\I18N\Plural;

use ASAP\I18N\PluralRuleInterface;

/**
 * PUBLIC PLURAL RULE
 *
 * Role:
 *   Select Russian plural categories.
 *
 * Responsibility:
 *   Preserve the legacy ASAP ability to support complex plural languages.
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
