<?php
declare(strict_types=1);

namespace Opus\I18n;

use Opus\File\StructuredFileLoader;
use Opus\I18n\Plural\PluralRuleRegistry;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;
use Opus\Profiler\Trace;

final readonly class ApplicationTranslationRuntime
    implements TranslationRuntimeInterface, ApplicationTranslationRuntimeInterface
{
    public const CONTRACT = 'OPUS_APPLICATION_I18N_RUNTIME_V2';
    public const MISSING_MESSAGE_MARKER = '⚠';

    private Translator $translator;
    private Locale $activeLocale;
    private string $activeModule;
    private string $siteRoot;

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
        $this->siteRoot = str_replace('\\', '/', dirname($realRoot));
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
        $canonicalKey = (new I18nKey($key))->value;

        try {
            return $this->translator->translate(
                $canonicalKey,
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

            $this->recordMissingMessage($canonicalKey);

            return self::MISSING_MESSAGE_MARKER . ' ' . $canonicalKey;
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

    private function recordMissingMessage(string $key): void
    {
        $traceId = strtolower(trim((string) getenv('OPUS_TRACE_ID')));
        if (preg_match('/^[a-f0-9]{16,64}$/D', $traceId) !== 1) {
            $traceId = '';
        }

        $context = [
            'error_code' => 'OPUS_I18N_MESSAGE_MISSING',
            'i18n_key' => $key,
            'locale' => $this->activeLocale->value,
            'module' => $this->activeModule,
        ];

        try {
            [$logDir, $logFile] = $this->applicationLogTarget();
            (new Logger($logDir, $logFile))->warning(
                'opus.i18n',
                'message.missing',
                $context,
                $traceId === '' ? null : $traceId
            );
        } catch (\Throwable) {
        }

        if ($traceId === '') {
            return;
        }

        try {
            $trace = new Trace($traceId);
            $trace->addEvent(
                'i18n',
                'message.missing',
                $context,
                'warning'
            );
            $trace->finish();
            (new Profiler(
                $this->siteRoot . '/var/profiler/runtime'
            ))->writeTrace(
                $trace,
                [
                    'component' => self::class,
                    'status' => 'warning',
                    'trace_id' => $traceId,
                ]
            );
        } catch (\Throwable) {
        }
    }

    /** @return array{0:string,1:string} */
    private function applicationLogTarget(): array
    {
        $relative = 'var/logs/opus.log';
        $configFile = $this->siteRoot . '/config/site.json';

        try {
            $config = StructuredFileLoader::instance()->read($configFile);
            $development = is_array($config['development_server'] ?? null)
                ? $config['development_server']
                : [];
            $diagnostics = is_array($development['diagnostics'] ?? null)
                ? $development['diagnostics']
                : [];
            $configured = trim(str_replace(
                '\\',
                '/',
                (string) ($diagnostics['log'] ?? '')
            ));
            if (
                $configured !== ''
                && !str_starts_with($configured, '/')
                && !preg_match('/^[A-Za-z]:\//', $configured)
                && !str_contains($configured, '..')
            ) {
                $relative = $configured;
            }
        } catch (\Throwable) {
        }

        $absolute = $this->siteRoot . '/' . ltrim($relative, '/');

        return [dirname($absolute), basename($absolute)];
    }
}
