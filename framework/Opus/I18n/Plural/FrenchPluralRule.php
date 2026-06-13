<?php

declare(strict_types=1);

namespace Opus\I18n\Plural;

use ASAP\I18n\PluralRuleInterface;

/*
 * OPUS_REFBOOK:
 *   domain: I18N
 *   role: Class FrenchPluralRule belongs to the I18N Opus framework domain.
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
 *   Select French plural categories.
 *
 * Contract:
 *   French uses `one` for 0 and 1, `other` otherwise.
 *
 * Since:
 *   P112D4A
 */
final class FrenchPluralRule implements PluralRuleInterface
{
    public function select(int $count): string
    {
        return $count === 0 || $count === 1 ? 'one' : 'other';
    }
}
