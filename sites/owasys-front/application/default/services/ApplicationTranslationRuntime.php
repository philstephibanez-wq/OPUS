<?php
declare(strict_types=1);

namespace Opus\I18n;

use Opus\I18n\Plural\PluralRuleRegistry;
use Opus\Log\Logger;

/**
 * OWASYS-local translation runtime compatibility shim.
 *
 * Missing messages remain visible and diagnosable:
 * - UI: "⚠ <exact.i18n.key>"
 * - log: structured i18n.message_missing event with exact key, locale,
 *   module and current trace_id.
 *
 * All non-missing I18n failures remain exceptions.
 */
final readonly class ApplicationTranslationRuntime
    implements TranslationRuntimeInterface, ApplicationTranslationRuntimeInterface
{
    public const CONTRACT = 'OPUS_APPLICATION_I18N_RUNTIME_V2';
    public const OWASYS_MISSING_MESSAGE_MARKER = '⚠';

    private Translator $translator;
    private Locale $activeLocale;
    private string $activeModule;
    private Logger $logger;

    public function __construct(
        string $applicationRoot,
        string $module,
        string $locale,
        ?CatalogLoader $loader = null,
        ?PluralRuleRegistry $rules = null
    ) {
        $applicationRoot = rtrim(str_replace('\\', '/', $applicationRoot), '/');
        $realRoot = realpath($applicationRoot);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw TranslationException::because(
                'OPUS_I18N_APPLICATION_ROOT_INVALID',
                $applicationRoot
            );
        }
        $module = trim($module);
        if ($module === '' || preg_match('/^[a-z][a-z0-9_-]*$/', $module) !== 1) {
            throw TranslationException::because('OPUS_I18N_MODULE_INVALID', $module);
        }

        $this->activeLocale = new Locale($locale);
        $this->activeModule = $module;

        $siteRoot = dirname(str_replace('\\', '/', $realRoot));
        $this->logger = new Logger(
            $siteRoot . '/var/logs',
            'owasys-front.log'
        );

        $loader ??= new CatalogLoader();
        $rules ??= new PluralRuleRegistry();

        $global = $loader->loadDirectory(
            $realRoot . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local',
            $this->activeLocale,
            'default',
            true
        );
        if (!$global instanceof Catalog) {
            throw TranslationException::because('OPUS_I18N_GLOBAL_CATALOG_REQUIRED');
        }

        $catalogs = [$global];
        if ($module !== 'default') {
            $moduleCatalog = $loader->loadDirectory(
                $realRoot . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'local',
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

    /** @param array<string,mixed> $parameters */
    public function translate(
        string $key,
        array $parameters = [],
        int|float|null $count = null,
        Gender|string|null $gender = null
    ): string {
        try {
            return $this->translator->translate(
                $key,
                $parameters,
                $count,
                $gender
            );
        } catch (TranslationException $error) {
            if (!str_starts_with(
                $error->getMessage(),
                'OPUS_I18N_MESSAGE_MISSING:'
            )) {
                throw $error;
            }

            $traceId = trim((string) getenv('OPUS_TRACE_ID'));
            $this->logger->warning(
                'i18n',
                'i18n.message_missing',
                [
                    'error_code' => 'OPUS_I18N_MESSAGE_MISSING',
                    'i18n_key' => $key,
                    'locale' => $this->activeLocale->value,
                    'module' => $this->activeModule,
                ],
                $traceId !== '' ? $traceId : null
            );

            return self::OWASYS_MISSING_MESSAGE_MARKER . ' ' . $key;
        }
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
