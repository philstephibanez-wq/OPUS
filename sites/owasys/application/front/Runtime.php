<?php
declare(strict_types=1);

use Opus\Http\Response;
use Opus\I18n\LayeredApplicationTranslationRuntime;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Template\ScoreTemplateRenderer;

/** Isolated OWASYS presentation runtime. */
final class OwasysFrontRuntime implements OwasysProcessRuntimeInterface
{
    private readonly OwasysRuntimeController $controller;
    private readonly OwasysCreationController $creation;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig
    ) {
        $opusRoot = dirname(dirname($siteRoot));
        $session = new OwasysAuthSession();
        $security = new OwasysRuntimeSecurity($siteRoot, $siteConfig);
        $renderer = new OwasysScorePageRenderer($siteRoot);
        $registry = new OwasysRegistryModel(
            $siteRoot,
            $opusRoot,
            OwasysApplicationSingletonInspector::instance($opusRoot)
        );

        $this->controller = new OwasysRuntimeController(
            $siteRoot,
            $siteConfig,
            $session,
            $security,
            $renderer
        );
        $this->creation = new OwasysCreationController(
            $siteRoot,
            $siteConfig,
            $session,
            $security,
            $renderer,
            $registry,
            new OwasysApplicationCreationModel($siteRoot, $registry)
        );
    }

    public function mode(): string
    {
        return 'front';
    }

    public function run(): void
    {
        $path = '/' . trim($this->requestPath(), '/');
        if ($path === '/api' || str_starts_with($path, '/api/')) {
            throw new RuntimeException('OWASYS_FRONT_RUNTIME_API_FORBIDDEN');
        }
        if ($this->creation->matchesCurrentRequest()) {
            $this->creation->run();
            return;
        }
        $this->controller->run();
    }

    public function fail(Throwable $error, string $traceId): void
    {
        $locale = $this->locale();
        $i18n = new LayeredApplicationTranslationRuntime(
            $this->siteRoot . '/application/shared',
            'default',
            $locale
        );
        $renderer = new ScoreTemplateRenderer(
            $this->siteRoot . '/application/front',
            $i18n
        );
        Response::html($renderer->render(
            'default/templates/runtime-error.score',
            [
                'locale' => $locale,
                'error' => [
                    'code' => $this->safeErrorCode($error),
                    'trace_id' => $traceId,
                ],
                'assets' => [
                    'score_css' => '/asset/css/owasys.css',
                    'theme_css' => '/asset/themes/owasys/css/theme.css',
                ],
            ]
        ), 500)->send();
    }

    private function locale(): string
    {
        $registry = new OwasysLocaleRegistry($this->siteConfig);
        $path = trim($this->requestPath(), '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $explicit = $registry->resolveExplicit((string) ($segments[0] ?? ''));
        if (is_string($explicit)) {
            return $explicit;
        }

        return BrowserLocaleNegotiator::forLocales(
            $registry->codes(),
            (string) ($this->siteConfig['default_locale'] ?? 'fr-FR')
        )->negotiate(
            is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null)
                ? $_SERVER['HTTP_ACCEPT_LANGUAGE']
                : null
        )->value;
    }

    private function requestPath(): string
    {
        $path = parse_url(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        );
        return is_string($path) ? rawurldecode($path) : '/';
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OWASYS_FRONT_RUNTIME_FAILED';
    }
}
