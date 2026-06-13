<?php

declare(strict_types=1);

namespace Opus\I18n;

/*
 * OPUS_REFBOOK:
 *   domain: I18N
 *   role: Interface PluralRuleInterface belongs to the I18N Opus framework domain.
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
