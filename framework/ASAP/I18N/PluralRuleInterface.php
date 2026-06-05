<?php

declare(strict_types=1);

namespace ASAP\I18N;

/**
 * PUBLIC CONTRACT
 *
 * Role:
 *   Select one plural category for a numeric count.
 *
 * Responsibility:
 *   Isolate language-specific plural rules from translation catalogs.
 *
 * Contract:
 *   Rule selection only. No message lookup and no rendering.
 *
 * Since:
 *   P112D4A
 */
interface PluralRuleInterface
{
    /**
     * PUBLIC API
     *
     * @param int $count Numeric count.
     *
     * @return string Plural category such as one, few, many or other.
     */
    public function select(int $count): string;
}
