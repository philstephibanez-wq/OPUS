<?php

declare(strict_types=1);

namespace ASAP\I18N;

use ASAP\I18N\Plural\EnglishPluralRule;
use ASAP\I18N\Plural\FrenchPluralRule;
use ASAP\I18N\Plural\RussianPluralRule;
use ASAP\I18N\Plural\SpanishPluralRule;

/**
 * PUBLIC LEGACY-ALIGNED I18N FACADE
 *
 * Role:
 *   Preserve the original ASAP `I18N\I18n` entry point.
 *
 * Responsibility:
 *   Provide locale-bound translation and pluralization over typed catalogs.
 *
 * Contract:
 *   No silent language fallback. Missing locale/catalog/key fails explicitly.
 *
 * Since:
 *   P112D4C
 */
final class I18n
{
    private Translator $translator;

    public function __construct(string $catalogRoot, string $locale)
    {
        $locale = strtolower(str_replace('_', '-', trim($locale)));
        $catalog = rtrim($catalogRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'asap.' . $locale . '.json';

        $rule = match ($locale) {
            'fr' => new FrenchPluralRule(),
            'en' => new EnglishPluralRule(),
            'es' => new SpanishPluralRule(),
            'ru' => new RussianPluralRule(),
            default => throw TranslationException::because('ASAP_I18N_PLURAL_RULE_MISSING', $locale),
        };

        $this->translator = new Translator((new JsonTranslationCatalogLoader())->load($catalog), $rule);
    }

    /**
     * @param array<string,string|int|float> $params
     */
    public function translate(string $key, array $params = []): string
    {
        return $this->translator->translate($key, $params);
    }

    /**
     * @param array<string,string|int|float> $params
     */
    public function plural(string $key, int $count, array $params = []): string
    {
        return $this->translator->plural($key, $count, $params);
    }
}
