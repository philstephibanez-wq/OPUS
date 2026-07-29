<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\Http\Response;
use Opus\Http\UrlBuilder;
use Opus\I18n\BrowserLocaleNegotiator;

/** Server-rendered, read-only source browser for the selected OPUS application. */
final class OwasysSourceController
{
    private const FSM_SESSION_KEY = 'opus.fsm.owasys-front';

    private readonly OwasysLocaleRegistry $locales;
    private readonly OwasysNavigationBuilder $navigation;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig,
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security,
        private readonly OwasysScorePageRenderer $renderer,
        private readonly OwasysSourceModel $source
    ) {
        $this->locales = new OwasysLocaleRegistry($siteConfig);
        $this->navigation = new OwasysNavigationBuilder($security);
    }

    public function matchesCurrentRequest(): bool
    {
        [, $route] = $this->resolveRequest();
        return $route === 'source' || str_starts_with($route, 'source/');
    }

    public function run(): void
    {
        $this->startSession();
        [$locale, $route] = $this->resolveRequest();
        if ($route !== 'source' && !str_starts_with($route, 'source/')) {
            throw new RuntimeException('OWASYS_SOURCE_ROUTE_MISMATCH');
        }
        $sourcePath = $route === 'source'
            ? ''
            : substr($route, strlen('source/'));
        $identity = $this->session->user();
        if (!is_array($identity)) {
            $this->redirect($locale, 'login');
        }
        $this->security->assertAllowed($identity, 'source', 'open');
        $currentApp = $this->session->currentApp();
        if (!is_array($currentApp)) {
            $this->redirect($locale, 'applications');
        }

        $fsmConfig = $this->fsmConfig();
        $fsm = FsmSiteLoader::processorForSiteRoot($this->siteRoot);
        $store = new FsmSessionStore(self::FSM_SESSION_KEY);
        $store->restore($fsm);
        $state = $this->enterSourceState(
            $fsm,
            $store,
            $locale,
            $sourcePath,
            $identity,
            $currentApp
        );
        $this->applyProfilerSignal(
            $fsm,
            $store,
            $locale,
            $sourcePath,
            $identity,
            $currentApp
        );
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET') {
            throw new RuntimeException('OWASYS_SOURCE_METHOD_NOT_ALLOWED');
        }

        $listing = [
            'contract' => 'OPUS_SITE_SOURCE_LIST_V1',
            'files' => [],
            'truncated' => false,
        ];
        $selected = null;
        $errorCode = null;
        try {
            if ($sourcePath !== '') {
                if ($this->expectsJson()) {
                    $selected = $this->source->read(
                        (string) ($currentApp['id'] ?? ''),
                        $sourcePath,
                        $identity
                    );
                    Response::json([
                        'contract' => 'OWASYS_SOURCE_SELECTION_V1',
                        'selected' => $selected,
                    ])->send();
                    return;
                }
                $browse = $this->source->browse(
                    (string) ($currentApp['id'] ?? ''),
                    $sourcePath,
                    $identity
                );
                $listing = $browse['listing'];
                $selected = $browse['selected'];
            } else {
                $listing = $this->source->list(
                    (string) ($currentApp['id'] ?? ''),
                    $identity
                );
            }
        } catch (Throwable $error) {
            $errorCode = $this->safeErrorCode($error);
            if ($this->expectsJson()) {
                Response::json([
                    'contract' => 'OWASYS_SOURCE_SELECTION_ERROR_V1',
                    'error_code' => $errorCode,
                ], 422)->send();
                return;
            }
            http_response_code(422);
        }

        $this->render(
            $fsmConfig,
            $state,
            $locale,
            $identity,
            $currentApp,
            $listing,
            $selected,
            $errorCode
        );
    }

    /** @param array<string,mixed> $identity @param array<string,mixed> $currentApp */
    private function enterSourceState(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        string $locale,
        string $sourcePath,
        array $identity,
        array $currentApp
    ): string {
        $current = $fsm->currentState();
        $context = [
            'identity' => $identity,
            'is_authenticated' => true,
            'roles' => is_array($identity['roles'] ?? null)
                ? $identity['roles']
                : [],
            'current_app' => $currentApp,
            'has_current_app' => true,
            'locale' => $locale,
            'source_path' => $sourcePath,
        ];
        if ($current !== 'source') {
            $transition = $fsm->transition($current, 'open_source', $context);
            $current = (string) ($transition['to_state'] ?? '');
            if ($current !== 'source') {
                throw new RuntimeException('OWASYS_SOURCE_FSM_STATE_INVALID');
            }
        }
        $previousLocale = array_key_exists('locale', $fsm->memory())
            ? (string) $fsm->peek('locale')
            : '';
        $rememberedPath = array_key_exists('source_path', $fsm->memory())
            ? (string) $fsm->peek('source_path')
            : '';
        if ($sourcePath === '' && $previousLocale !== ''
            && $previousLocale !== $locale && $rememberedPath !== '') {
            $fsm->transition('source', 'change_locale', $context);
            $store->persist($fsm);
            $this->redirect($locale, $this->sourceRoute($rememberedPath));
        }
        $fsm->transition('source', 'open_source_file', $context);
        $store->persist($fsm);
        return $current;
    }

    /**
     * @param array<string,mixed> $fsmConfig
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     * @param array<string,mixed> $listing
     * @param array<string,mixed>|null $selected
     */
    private function render(
        array $fsmConfig,
        string $state,
        string $locale,
        array $identity,
        array $currentApp,
        array $listing,
        ?array $selected,
        ?string $errorCode
    ): void {
        $basePath = $this->basePath();
        $routeUrl = fn (string $target): string => $this->routeUrl(
            $locale,
            $target
        );
        $selectedPath = (string) ($selected['path'] ?? '');
        $files = [];
        foreach ((array) ($listing['files'] ?? []) as $file) {
            if (!is_array($file)) {
                continue;
            }
            $path = (string) ($file['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $files[] = [
                'path' => $path,
                'name' => basename($path),
                'bytes' => (string) ($file['bytes'] ?? '0'),
                'selected' => $path === $selectedPath,
                'url' => $this->sourceUrl($locale, $path),
            ];
        }

        $data = [
            'page' => ['title' => '', 'summary' => ''],
            'fsm' => ['state' => $state, 'module' => 'source'],
            'identity' => [
                'authenticated' => true,
                'label' => (string) ($identity['label'] ?? ''),
                'primary_role' => (string) (
                    $identity['roles'][0] ?? $identity['profile'] ?? ''
                ),
            ],
            'current_app' => [
                'present' => true,
                'id' => (string) ($currentApp['id'] ?? ''),
                'name' => (string) (
                    $currentApp['name'] ?? $currentApp['id'] ?? ''
                ),
                'kind' => (string) ($currentApp['kind'] ?? ''),
                'root' => (string) ($currentApp['root_path'] ?? ''),
            ],
            'locale' => [
                'code' => $locale,
                'name' => $this->locales->name($locale),
                'flag' => $basePath . '/asset/flags/'
                    . rawurlencode($this->locales->flagCode($locale)) . '.svg',
            ],
            'locales' => array_map(
                fn (string $code): array => [
                    'code' => $code,
                    'name' => $this->locales->name($code),
                    'flag' => $basePath . '/asset/flags/'
                        . rawurlencode($this->locales->flagCode($code)) . '.svg',
                    'url' => $this->routeUrl(
                        $code,
                        $selectedPath === ''
                            ? 'source'
                            : $this->sourceRoute($selectedPath)
                    ),
                    'active' => $code === $locale,
                ],
                $this->locales->codes()
            ),
            'assets' => [
                'score_css' => $basePath . '/asset/css/owasys.css',
                'theme_css' => $basePath
                    . '/asset/themes/owasys/css/theme.css?v=p117q',
                'language_css' => $basePath
                    . '/asset/css/language-switcher.css',
                'password_js' => $basePath
                    . '/asset/js/password-visibility.js',
                'source_codemirror_js' => $basePath
                    . '/asset/vendor/codemirror/owasys-codemirror.js',
                'source_browser_js' => $basePath
                    . '/asset/js/source-browser.js',
            ],
            'urls' => [
                'home' => $this->routeUrl($locale, 'applications'),
                'login' => $this->routeUrl($locale, 'login'),
                'logout' => $this->routeUrl($locale, 'logout'),
                'account' => $this->routeUrl($locale, 'account/password'),
                'applications' => $this->routeUrl($locale, 'applications'),
                'current' => $this->routeUrl($locale, 'source'),
            ],
            'navigation' => $this->navigation->build(
                $fsmConfig,
                $identity,
                $state,
                true,
                $routeUrl
            ),
            'source' => [
                'browser_enabled' => true,
                'empty' => $files === [],
                'files' => $files,
                'truncated' => ($listing['truncated'] ?? false) === true,
                'selected' => is_array($selected),
                'unselected' => !is_array($selected),
                'path' => $selectedPath,
                'bytes' => (string) ($selected['bytes'] ?? ''),
                'sha256' => (string) ($selected['sha256'] ?? ''),
                'content' => (string) ($selected['content'] ?? ''),
                'has_error' => $errorCode !== null,
                'error_code' => $errorCode ?? '',
            ],
        ];

        header('Content-Type: text/html; charset=UTF-8');
        $this->renderer->emit('source/templates/index.score', $data);
    }

    /** @return array{0:string,1:string} */
    private function resolveRequest(): array
    {
        $path = parse_url(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        );
        $path = is_string($path) ? rawurldecode($path) : '/';
        $segments = trim($path, '/') === ''
            ? []
            : explode('/', trim($path, '/'));
        if (($segments[0] ?? '') === 'owasys') {
            array_shift($segments);
        }
        $default = (string) ($this->siteConfig['default_locale'] ?? 'fr-FR');
        $negotiator = BrowserLocaleNegotiator::forLocales(
            $this->locales->codes(),
            $default
        );
        $first = (string) ($segments[0] ?? '');
        $explicit = $this->locales->resolveExplicit($first);
        if (is_string($explicit)) {
            $locale = $explicit;
            array_shift($segments);
        } else {
            $locale = $negotiator->negotiate(
                is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null)
                    ? $_SERVER['HTTP_ACCEPT_LANGUAGE']
                    : null
            )->value;
        }
        return [$locale, implode('/', $segments)];
    }

    /** @return array<string,mixed> */
    private function fsmConfig(): array
    {
        $navigation = is_array($this->siteConfig['navigation'] ?? null)
            ? $this->siteConfig['navigation']
            : [];
        $relative = trim(str_replace(
            '\\',
            '/',
            (string) ($navigation['fsm'] ?? '')
        ), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException('OWASYS_SOURCE_FSM_PATH_INVALID');
        }
        return StructuredFileLoader::instance()->read(
            $this->siteRoot . '/' . $relative
        );
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $auth = is_array($this->siteConfig['auth'] ?? null)
            ? $this->siteConfig['auth']
            : [];
        $name = (string) ($auth['session_name'] ?? 'OWASYS_LOCAL_SESSION');
        if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
            throw new RuntimeException('OWASYS_SESSION_NAME_INVALID');
        }
        session_name($name);
        session_start();
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OWASYS_SOURCE_FAILED';
    }

    private function expectsJson(): bool
    {
        return str_contains(
            strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')),
            'application/json'
        );
    }

    private function routeUrl(string $locale, string $route): string
    {
        $segments = array_values(array_filter(
            explode('/', trim($route, '/')),
            'strlen'
        ));
        array_unshift($segments, $locale);
        return (new UrlBuilder($this->basePath()))->build($segments);
    }

    private function sourceUrl(string $locale, string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), 'strlen');
        if ($segments === []) {
            throw new RuntimeException('OWASYS_SOURCE_PATH_INVALID');
        }
        return (new UrlBuilder($this->basePath()))->build(
            array_merge([$locale, 'source'], array_values($segments))
        );
    }

    private function sourceRoute(string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), 'strlen');
        if ($segments === []) {
            throw new RuntimeException('OWASYS_SOURCE_PATH_INVALID');
        }
        return 'source/' . implode('/', $segments);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     */
    private function applyProfilerSignal(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        string $locale,
        string $sourcePath,
        array $identity,
        array $currentApp
    ): void {
        $raw = $_GET['profiler'] ?? null;
        if ($raw !== null && (string) $raw !== '1') {
            throw new RuntimeException('OWASYS_PROFILER_OPTION_INVALID');
        }
        $isOpen = array_key_exists('profiler_open', $fsm->memory())
            && $fsm->peek('profiler_open') === true;
        $context = [
            'identity' => $identity,
            'is_authenticated' => true,
            'roles' => is_array($identity['roles'] ?? null)
                ? $identity['roles']
                : [],
            'current_app' => $currentApp,
            'has_current_app' => true,
            'locale' => $locale,
            'source_path' => $sourcePath,
            'return_url' => $this->routeUrl(
                $locale,
                $sourcePath === '' ? 'source' : $this->sourceRoute($sourcePath)
            ),
            'trace_id' => trim((string) getenv('OPUS_TRACE_ID')),
            'profiler_open' => true,
            'profiler_closed' => false,
        ];
        if ((string) $raw === '1' && !$isOpen) {
            $this->security->assertAllowed($identity, 'profiler', 'view');
            $fsm->transition('source', 'open_profiler', $context);
            $store->persist($fsm);
        } elseif ($raw === null && $isOpen) {
            $context['profiler_open'] = false;
            $context['profiler_closed'] = true;
            $fsm->transition('source', 'close_profiler', $context);
            $store->persist($fsm);
        }
    }

    private function basePath(): string
    {
        $script = str_replace('\\', '/', (string) (
            $_SERVER['SCRIPT_NAME'] ?? ''
        ));
        $directory = str_replace('\\', '/', dirname($script));
        return in_array($directory, ['/', '.', ''], true)
            ? ''
            : rtrim($directory, '/');
    }

    private function redirect(string $locale, string $route): never
    {
        header('Location: ' . $this->routeUrl($locale, $route), true, 303);
        exit;
    }
}
