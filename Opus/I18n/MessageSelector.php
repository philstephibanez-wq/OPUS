<?php
declare(strict_types=1);

namespace Opus\I18n;

use Opus\I18n\Plural\PluralRuleInterface;

final class MessageSelector
{
    private const GENDERS = ['masculine', 'feminine', 'neuter'];
    private const PLURALS = [
        'zero', 'one', 'two', 'few', 'many', 'other',
    ];

    public function select(
        string|array $entry,
        PluralRuleInterface $pluralRule,
        int|float|null $count,
        Gender|string|null $gender,
        string $key
    ): string {
        if (is_string($entry)) {
            return $this->nonEmpty($entry, $key);
        }

        $forms = is_array($entry['forms'] ?? null)
            ? $entry['forms']
            : $entry;

        $genderKeys = array_values(
            array_intersect(self::GENDERS, array_keys($forms))
        );

        if ($genderKeys !== []) {
            if ($gender === null) {
                throw TranslationException::because(
                    'OPUS_I18N_GENDER_REQUIRED',
                    $key
                );
            }

            $genderValue = $gender instanceof Gender
                ? $gender->value
                : Gender::fromInput($gender)->value;

            if (!array_key_exists($genderValue, $forms)) {
                throw TranslationException::because(
                    'OPUS_I18N_GENDER_FORM_MISSING',
                    $key . ':' . $genderValue
                );
            }

            $forms = $forms[$genderValue];

            if (is_string($forms)) {
                return $this->nonEmpty($forms, $key);
            }

            if (!is_array($forms)) {
                throw TranslationException::because(
                    'OPUS_I18N_GENDER_FORM_INVALID',
                    $key . ':' . $genderValue
                );
            }
        }

        $pluralKeys = array_values(
            array_intersect(self::PLURALS, array_keys($forms))
        );

        if ($pluralKeys !== []) {
            if ($count === null) {
                throw TranslationException::because(
                    'OPUS_I18N_COUNT_REQUIRED',
                    $key
                );
            }

            $category = $pluralRule->select($count);

            if (!array_key_exists($category, $forms)) {
                throw TranslationException::because(
                    'OPUS_I18N_PLURAL_FORM_MISSING',
                    $key . ':' . $category
                );
            }

            $selected = $forms[$category];

            if (!is_string($selected)) {
                throw TranslationException::because(
                    'OPUS_I18N_PLURAL_FORM_INVALID',
                    $key . ':' . $category
                );
            }

            return $this->nonEmpty($selected, $key);
        }

        if (is_string($forms['value'] ?? null)) {
            return $this->nonEmpty($forms['value'], $key);
        }

        throw TranslationException::because(
            'OPUS_I18N_MESSAGE_FORMS_INVALID',
            $key
        );
    }

    private function nonEmpty(string $message, string $key): string
    {
        if ($message === '') {
            throw TranslationException::because(
                'OPUS_I18N_MESSAGE_EMPTY',
                $key
            );
        }

        return $message;
    }
}
