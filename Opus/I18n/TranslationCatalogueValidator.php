<?php
declare(strict_types=1);

namespace Opus\I18n;

use RuntimeException;

/**
 * Validates that required UI keys exist in every configured locale catalogue.
 */
final class TranslationCatalogueValidator
{
    public const CONTRACT = 'OPUS_I18N_CATALOGUE_VALIDATOR_V1';

    /**
     * @param array<string,array<string,string>> $catalogues
     * @param list<I18nKey|string> $requiredKeys
     */
    public function validate(array $catalogues, array $requiredKeys): void
    {
        if ($catalogues === []) {
            throw new RuntimeException('OPUS_I18N_CATALOGUES_EMPTY');
        }

        foreach ($requiredKeys as $requiredKey) {
            $key = $requiredKey instanceof I18nKey ? $requiredKey->value : (new I18nKey((string) $requiredKey))->value;

            foreach ($catalogues as $locale => $messages) {
                $value = $messages[$key] ?? null;
                if (!is_string($value) || trim($value) === '') {
                    throw new RuntimeException('OPUS_I18N_KEY_MISSING:' . $locale . ':' . $key);
                }
                if ($value === $key) {
                    throw new RuntimeException('OPUS_I18N_KEY_ECHO_FORBIDDEN:' . $locale . ':' . $key);
                }
            }
        }
    }
}
