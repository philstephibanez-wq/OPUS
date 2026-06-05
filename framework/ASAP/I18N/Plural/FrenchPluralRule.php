<?php

declare(strict_types=1);

namespace ASAP\I18N\Plural;

use ASAP\I18N\PluralRuleInterface;

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
