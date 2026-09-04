<?php
declare(strict_types=1);

namespace Opus\I18n;

use Opus\I18n\Plural\PluralRuleRegistry;
use Opus\Log\LoggerInterface;
use Opus\Profiler\ProfilerInterface;

final readonly class ApplicationTranslationRuntime
    implements TranslationRuntimeInterface, ApplicationTranslationRuntimeInterface
{
    public const CONTRACT = 'OPUS_APPLICATION_I18N_RUNTIME_V3';

    private Translator $translator;
    private Locale $activeLocale;
    private string $activeModule;

    public function __construct(
        string $applicationRoot,
        string $module,
        string $locale,
        ?CatalogLoader $loader = null,
        ?PluralRuleRegistry $rules = null,
        private ?LoggerInterface $logger = null,
        private ?ProfilerInterface $profiler = null,
        private ?string $parentSpanId = null
    ) {
        $applicationRoot = rtrim(str_replace('\\', '/', $applicationRoot), '/');
        $realRoot = realpath($applicationRoot);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw TranslationException::because('OPUS_I18N_APPLICATION_ROOT_INVALID', $applicationRoot);
        }
        $module = trim($module);
        if ($module === '' || preg_match('/^[a-z][a-z0-9_-]*$/', $module) !== 1) {
            throw TranslationException::because('OPUS_I18N_MODULE_INVALID', $module);
        }
        $this->activeLocale = new Locale($locale);
        $this->activeModule = $module;
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
            if ($moduleCatalog instanceof Catalog) $catalogs[] = $moduleCatalog;
        }
        $this->translator = new Translator(
            new CatalogStack(...$catalogs),
            $rules->forLocale($this->activeLocale)
        );
    }

    public function translate(string $key, array $parameters = [], int|float|null $count = null, Gender|string|null $gender = null): string
    {
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
            $context = [
                'error_code' => 'OPUS_I18N_MESSAGE_MISSING',
                'i18n_key' => $key,
                'locale' => $this->activeLocale->value,
                'module' => $this->activeModule,
                'trace_id' => $traceId,
            ];

            $this->logger?->warning(
                'i18n',
                'message.missing',
                $context,
                $traceId !== '' ? $traceId : null
            );

            if ($this->profiler?->getActiveTrace() !== null) {
                $this->profiler->event(
                    'i18n',
                    'message.missing',
                    $context,
                    'warning',
                    null,
                    $this->parentSpanId
                );
            }

            return '⚠ ' . $key;
        }
    }

    public function locale(): Locale { return $this->activeLocale; }
    public function module(): string { return $this->activeModule; }
}
