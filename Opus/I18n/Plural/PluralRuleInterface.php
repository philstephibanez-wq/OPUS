<?php
declare(strict_types=1);

namespace Opus\I18n\Plural;

interface PluralRuleInterface
{
    public function select(int|float $count): string;
}
