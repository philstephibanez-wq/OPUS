<?php

declare(strict_types=1);

namespace Opus\I18n;

use ASAP\I18n\Plural\EnglishPluralRule;
use ASAP\I18n\Plural\FrenchPluralRule;
use ASAP\I18n\Plural\RussianPluralRule;
use ASAP\I18n\Plural\SpanishPluralRule;

/*
 * OPUS_REFBOOK:
 *   domain: I18N
 *   role: Class I18n belongs to the I18N Opus framework domain.
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
 * PUBLIC LEGACY-ALIGNED I18N FACADE
 *
 * Role:
 *   Preserve the original Opus `I18N\I18n` entry point.
 *
 * Responsibility:
 *   Provide locale-bound translation and pluralization over typed catalogs.
 *
 * Contract:
 *   No silent language fallback. Missing locale/catalog/key fails explicitly.
 *
 * Since:
 *   P112D4C
 *
 * Legacy compatibility:
 *   P112O restores safe aliases: t(), dictionary(), getDictionary(), loadDictionary().
 */
final class I18n
{
    /** @var array<string,self> */
    private static array $instances = [];

    private Translator $translator;
    private TranslationCatalog $catalog;
    private string $catalogRoot;
    private string $locale;

    public function __construct(string $catalogRoot, string $locale)
    {
        $this->catalogRoot = rtrim($catalogRoot, DIRECTORY_SEPARATOR);
        $this->locale = strtolower(str_replace('_', '-', trim($locale)));
        $this->catalog = $this->loadCatalog($this->catalogRoot, $this->locale);
        $this->translator = new Translator($this->catalog, $this->pluralRule($this->locale));
    }

    public static function getInstance(string $catalogRoot, string $locale): self
    {
        $key = rtrim(str_replace('\\', '/', $catalogRoot), '/') . '::' . strtolower(str_replace('_', '-', trim($locale)));

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($catalogRoot, $locale);
        }

        return self::$instances[$key];
    }

    /** @return string[] */
    public function getAvalaibleLanguages(): array
    {
        $files = glob($this->catalogRoot . DIRECTORY_SEPARATOR . 'opus.*.json') ?: [];
        $languages = [];

        foreach ($files as $file) {
            if (preg_match('/asap\.([a-z]{2}(?:-[a-z]{2})?)\.json$/i', basename($file), $m) === 1) {
                $languages[] = strtolower($m[1]);
            }
        }

        sort($languages);

        return $languages;
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
    public function t(string $key, array $params = []): string
    {
        return $this->translate($key, $params);
    }

    /**
     * @param array<string,string|int|float> $params
     */
    public function plural(string $key, int $count, array $params = []): string
    {
        return $this->translator->plural($key, $count, $params);
    }

    /** @return array{locale:string,messages:array<string,string>,plurals:array<string,array<string,string>>} */
    public function dictionary(): array
    {
        return $this->catalog->toArray();
    }

    /** @return array{locale:string,messages:array<string,string>,plurals:array<string,array<string,string>>} */
    public function getDictionary(): array
    {
        return $this->dictionary();
    }

    public function loadDictionary(?string $locale = null): self
    {
        $nextLocale = $locale === null ? $this->locale : strtolower(str_replace('_', '-', trim($locale)));
        $this->locale = $nextLocale;
        $this->catalog = $this->loadCatalog($this->catalogRoot, $this->locale);
        $this->translator = new Translator($this->catalog, $this->pluralRule($this->locale));

        return $this;
    }

    private function loadCatalog(string $catalogRoot, string $locale): TranslationCatalog
    {
        $catalog = rtrim($catalogRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'opus.' . $locale . '.json';

        return (new JsonTranslationCatalogLoader())->load($catalog);
    }

    private function pluralRule(string $locale): PluralRuleInterface
    {
        return match ($locale) {
            'fr' => new FrenchPluralRule(),
            'en' => new EnglishPluralRule(),
            'es' => new SpanishPluralRule(),
            'ru' => new RussianPluralRule(),
            default => throw TranslationException::because('OPUS_I18N_PLURAL_RULE_MISSING', $locale),
        };
    }
}
