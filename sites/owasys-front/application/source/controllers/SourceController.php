<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSiteLoader;
use Opus\I18n\BrowserLocaleNegotiator;

/** Server-rendered, read-only source browser for the selected OPUS application. */
final class OwasysSourceController
{
    private const STATE_KEY = 'opus_fsm_state_owasys';

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
        return $route === 'source';
    }

    public function run(): void
    {
        $this->startSession();
        [$locale, $route] = $this->resolveRequest();
        if ($route !== 'source') {
            throw new RuntimeException('OWASYS_SOURCE_ROUTE_MISMATCH');
        }
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
        $state = $this->enterSourceState($fsm, $identity, $currentApp);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'POST'], true)) {
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
            $listing = $this->source->list(
                (string) ($currentApp['id'] ?? ''),
                $identity
            );
            if ($method === 'POST') {
                if (trim((string) ($_POST['owasys_action'] ?? ''))
                    !== 'source-read') {
                    throw new RuntimeException('OWASYS_SOURCE_ACTION_INVALID');
                }
                $selected = $this->source->read(
                    (string) ($currentApp['id'] ?? ''),
                    (string) ($_POST['owasys_source_path'] ?? ''),
                    $identity
                );
            }
        } catch (Throwable $error) {
            $errorCode = $this->safeErrorCode($error);
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
        array $identity,
        array $currentApp
    ): string {
        $current = trim((string) (
            $_SESSION[self::STATE_KEY] ?? $fsm->initialState()
        ));
        if (!$fsm->hasState($current)) {
            $current = $fsm->initialState();
        }
        if ($current !== 'source') {
            $transition = $fsm->transition($current, 'open_source', [
                'identity' => $identity,
                'is_authenticated' => true,
                'roles' => is_array($identity['roles'] ?? null)
                    ? $identity['roles']
                    : [],
                'current_app' => $currentApp,
                'has_current_app' => true,
            ]);
            $current = (string) ($transition['to_state'] ?? '');
            if ($current !== 'source') {
                throw new RuntimeException('OWASYS_SOURCE_FSM_STATE_INVALID');
            }
            $_SESSION[self::STATE_KEY] = $current;
        }
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
                'bytes' => (string) ($file['bytes'] ?? '0'),
                'selected' => $path === $selectedPath,
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
                    'url' => $this->routeUrl($code, 'source'),
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
                'empty' => $files === [],
                'files' => $files,
                'truncated' => ($listing['truncated'] ?? false) === true,
                'selected' => is_array($selected),
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

    private function routeUrl(string $locale, string $route): string
    {
        return $this->basePath() . '/' . rawurlencode($locale)
            . '/' . ltrim($route, '/');
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
