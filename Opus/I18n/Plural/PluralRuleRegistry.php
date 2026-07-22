<?php
declare(strict_types=1);

namespace Opus\I18n\Plural;

use Opus\I18n\Locale;
use Opus\I18n\TranslationException;

final class PluralRuleRegistry implements PluralRuleRegistryInterface
{
    public const CONTRACT = 'OPUS_I18N_CLDR_PLURAL_RULES_EU_UK_V1';

    public function forLocale(Locale $locale): PluralRuleInterface
    {
        $language = $locale->language;

        $selector = match ($language) {
            'fr' => static fn (int|float $n): string =>
                self::isInteger($n) && abs($n) >= 0 && abs($n) < 2
                    ? 'one'
                    : 'other',

            'pt' => static fn (int|float $n): string =>
                self::isInteger($n) && in_array(abs((int) $n), [0, 1], true)
                    ? 'one'
                    : 'other',

            'ru', 'uk' => static function (int|float $n): string {
                if (!self::isInteger($n)) {
                    return 'other';
                }

                $i = abs((int) $n);
                $mod10 = $i % 10;
                $mod100 = $i % 100;

                if ($mod10 === 1 && $mod100 !== 11) {
                    return 'one';
                }

                if (
                    $mod10 >= 2
                    && $mod10 <= 4
                    && !($mod100 >= 12 && $mod100 <= 14)
                ) {
                    return 'few';
                }

                return 'many';
            },

            'pl' => static function (int|float $n): string {
                if (!self::isInteger($n)) {
                    return 'other';
                }

                $i = abs((int) $n);
                if ($i === 1) {
                    return 'one';
                }

                $mod10 = $i % 10;
                $mod100 = $i % 100;

                if (
                    $mod10 >= 2
                    && $mod10 <= 4
                    && !($mod100 >= 12 && $mod100 <= 14)
                ) {
                    return 'few';
                }

                return 'many';
            },

            'hr' => static function (int|float $n): string {
                if (!self::isInteger($n)) {
                    return 'other';
                }

                $i = abs((int) $n);
                $mod10 = $i % 10;
                $mod100 = $i % 100;

                if ($mod10 === 1 && $mod100 !== 11) {
                    return 'one';
                }

                if (
                    $mod10 >= 2
                    && $mod10 <= 4
                    && !($mod100 >= 12 && $mod100 <= 14)
                ) {
                    return 'few';
                }

                return 'other';
            },

            'cs', 'sk' => static function (int|float $n): string {
                if (!self::isInteger($n)) {
                    return 'many';
                }

                $i = abs((int) $n);

                return $i === 1
                    ? 'one'
                    : ($i >= 2 && $i <= 4 ? 'few' : 'other');
            },

            'sl' => static function (int|float $n): string {
                if (!self::isInteger($n)) {
                    return 'few';
                }

                return match (abs((int) $n) % 100) {
                    1 => 'one',
                    2 => 'two',
                    3, 4 => 'few',
                    default => 'other',
                };
            },

            'ro' => static function (int|float $n): string {
                if (!self::isInteger($n)) {
                    return 'few';
                }

                $i = abs((int) $n);
                $mod100 = $i % 100;

                if ($i === 1) {
                    return 'one';
                }

                return $i === 0 || ($mod100 >= 1 && $mod100 <= 19)
                    ? 'few'
                    : 'other';
            },

            'lt' => static function (int|float $n): string {
                if (!self::isInteger($n)) {
                    return 'many';
                }

                $i = abs((int) $n);
                $mod10 = $i % 10;
                $mod100 = $i % 100;

                if ($mod10 === 1 && !($mod100 >= 11 && $mod100 <= 19)) {
                    return 'one';
                }

                if (
                    $mod10 >= 2
                    && $mod10 <= 9
                    && !($mod100 >= 11 && $mod100 <= 19)
                ) {
                    return 'few';
                }

                return 'other';
            },

            'lv' => static function (int|float $n): string {
                if (!self::isInteger($n)) {
                    return 'other';
                }

                $i = abs((int) $n);
                $mod10 = $i % 10;
                $mod100 = $i % 100;

                if ($mod10 === 0 || ($mod100 >= 11 && $mod100 <= 19)) {
                    return 'zero';
                }

                return $mod10 === 1 && $mod100 !== 11
                    ? 'one'
                    : 'other';
            },

            'ga' => static function (int|float $n): string {
                if (!self::isInteger($n)) {
                    return 'other';
                }

                return match (abs((int) $n)) {
                    1 => 'one',
                    2 => 'two',
                    3, 4, 5, 6 => 'few',
                    7, 8, 9, 10 => 'many',
                    default => 'other',
                };
            },

            'mt' => static function (int|float $n): string {
                if (!self::isInteger($n)) {
                    return 'other';
                }

                $i = abs((int) $n);
                $mod100 = $i % 100;

                if ($i === 1) {
                    return 'one';
                }

                if ($i === 0 || ($mod100 >= 2 && $mod100 <= 10)) {
                    return 'few';
                }

                return $mod100 >= 11 && $mod100 <= 19
                    ? 'many'
                    : 'other';
            },

            'bg', 'da', 'de', 'el', 'en', 'es', 'et', 'fi',
            'hu', 'it', 'nl', 'sv' =>
                static fn (int|float $n): string =>
                    self::isInteger($n) && abs((int) $n) === 1
                        ? 'one'
                        : 'other',

            default => throw TranslationException::because(
                'OPUS_I18N_PLURAL_RULE_MISSING',
                $locale->value
            ),
        };

        return new class($selector) implements PluralRuleInterface {
            /** @param \Closure(int|float):string $selector */
            public function __construct(private readonly \Closure $selector)
            {
            }

            public function select(int|float $count): string
            {
                return ($this->selector)($count);
            }
        };
    }

    private static function isInteger(int|float $number): bool
    {
        return is_int($number) || floor($number) === $number;
    }
}
