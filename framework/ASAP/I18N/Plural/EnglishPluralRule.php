<?php

declare(strict_types=1);

namespace ASAP\I18N\Plural;

use ASAP\I18N\PluralRuleInterface;

/**
 * PUBLIC PLURAL RULE
 *
 * Role:
 *   Select English plural categories.
 *
 * Contract:
 *   English uses `one` for count 1, `other` otherwise.
 *
 * Since:
 *   P112D4A
 */
final class EnglishPluralRule implements PluralRuleInterface
{
    public function select(int $count): string
    {
        return $count === 1 ? 'one' : 'other';
    }
}
