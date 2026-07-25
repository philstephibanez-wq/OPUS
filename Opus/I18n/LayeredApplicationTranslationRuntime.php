<?php
declare(strict_types=1);

namespace Opus\I18n;

use Opus\I18n\Plural\PluralRuleRegistry;

/** I18n runtime for application/shared + application/front layered sites. */
final readonly class LayeredApplicationTranslationRuntime implements
    TranslationRuntimeInterface,
    LayeredApplicationTranslationRuntimeInterface
{
    public const CONTRACT = 'OPUS_LAYERED_APPLICATION_I18N_RUNTIME_V1';

    private Translator $translator;
    private Locale $activeLocale;
    private string $activeModule;

    public function __construct(
        string $sharedRoot,
        string $module,
        string $locale,
        ?CatalogLoader $loader = null,
        ?PluralRuleRegistry $rules = null
    ) {
        $sharedRoot = rtrim(str_replace('\\', '/', $sharedRoot), '/');
        $realRoot = realpath($sharedRoot);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw TranslationException::because(
                'OPUS_LAYERED_I18N_SHARED_ROOT_INVALID',
                $sharedRoot
            );
        }

        $module = trim($module);
        if ($module === '' || preg_match('/^[a-z][a-z0-9_-]*$/', $module) !== 1) {
            throw TranslationException::because(
                'OPUS_I18N_MODULE_INVALID',
                $module
            );
        }

        $this->activeLocale = new Locale($locale);
        $this->activeModule = $module;
        $loader ??= new CatalogLoader();
        $rules ??= new PluralRuleRegistry();

        $global = $loader->loadDirectory(
            $realRoot . DIRECTORY_SEPARATOR . 'i18n'
                . DIRECTORY_SEPARATOR . 'default',
            $this->activeLocale,
            'default',
            true
        );
        if (!$global instanceof Catalog) {
            throw TranslationException::because(
                'OPUS_I18N_GLOBAL_CATALOG_REQUIRED'
            );
        }

        $catalogs = [$global];
        if ($module !== 'default') {
            $moduleCatalog = $loader->loadDirectory(
                $realRoot . DIRECTORY_SEPARATOR . 'i18n'
                    . DIRECTORY_SEPARATOR . 'modules'
                    . DIRECTORY_SEPARATOR . $module,
                $this->activeLocale,
                $module,
                false
            );
            if ($moduleCatalog instanceof Catalog) {
                $catalogs[] = $moduleCatalog;
            }
        }

        $this->translator = new Translator(
            new CatalogStack(...$catalogs),
            $rules->forLocale($this->activeLocale)
        );
    }

    public function translate(
        string $key,
        array $parameters = [],
        int|float|null $count = null,
        Gender|string|null $gender = null
    ): string {
        return $this->translator->translate(
            $key,
            $parameters,
            $count,
            $gender
        );
    }

    public function locale(): Locale
    {
        return $this->activeLocale;
    }

    public function module(): string
    {
        return $this->activeModule;
    }
}
