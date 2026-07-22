<?php
declare(strict_types=1);

namespace Opus\I18n;

use Opus\I18n\Plural\PluralRuleRegistry;

final class TranslationCatalogueValidator implements TranslationCatalogueValidatorInterface
{
    public const CONTRACT = 'OPUS_I18N_CATALOGUE_VALIDATOR_V2';

    /**
     * Compatibility API.
     *
     * @param array<string,array<string,string|array<mixed>>> $catalogues
     * @param list<I18nKey|string> $requiredKeys
     */
    public function validate(
        array $catalogues,
        array $requiredKeys
    ): void {
        if ($catalogues === []) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOGUES_EMPTY'
            );
        }

        foreach ($catalogues as $localeCode => $messages) {
            $locale = new Locale((string) $localeCode);
            $catalog = new Catalog($locale, 'validation', $messages);
            $rule = (new PluralRuleRegistry())->forLocale($locale);

            foreach ($requiredKeys as $requiredKey) {
                $key = $requiredKey instanceof I18nKey
                    ? $requiredKey->value
                    : (new I18nKey((string) $requiredKey))->value;

                $entry = $catalog->entry($key);
                $this->validateEntry($entry, $key, $rule);
            }
        }
    }

    private function validateEntry(
        string|array $entry,
        string $key,
        \Opus\I18n\Plural\PluralRuleInterface $rule
    ): void {
        if (is_string($entry)) {
            if ($entry === '' || $entry === $key) {
                throw TranslationException::because(
                    'OPUS_I18N_MESSAGE_INVALID',
                    $key
                );
            }
            return;
        }

        $forms = is_array($entry['forms'] ?? null)
            ? $entry['forms']
            : $entry;

        if ($forms === []) {
            throw TranslationException::because(
                'OPUS_I18N_MESSAGE_FORMS_EMPTY',
                $key
            );
        }

        $walk = function (array $node, string $path) use (&$walk): void {
            foreach ($node as $name => $value) {
                if (is_array($value)) {
                    $walk($value, $path . ':' . $name);
                    continue;
                }

                if (!is_string($value) || $value === '') {
                    throw TranslationException::because(
                        'OPUS_I18N_MESSAGE_FORM_INVALID',
                        $path . ':' . $name
                    );
                }
            }
        };

        $walk($forms, $key);

        // Exercise the rule so unsupported locales fail at validation time.
        $rule->select(0);
        $rule->select(1);
        $rule->select(2);
        $rule->select(5);
        $rule->select(21);
    }
}
