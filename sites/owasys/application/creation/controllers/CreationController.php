<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSiteLoader;
use Opus\File\StructuredFileLoader;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;

/** OWASYS application creation workflow. All writes cross REST then Composer. */
final class OwasysCreationController
{
    private const STATE_KEY = 'opus_fsm_state_owasys';

    private readonly OwasysLocaleRegistry $locales;
    private readonly OwasysNavigationBuilder $navigation;
    private readonly Logger $logger;
    private readonly Profiler $profiler;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig,
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security,
        private readonly OwasysScorePageRenderer $renderer,
        private readonly OwasysRegistryModel $registry,
        private readonly OwasysApplicationCreationModel $creation
    ) {
        $this->locales = new OwasysLocaleRegistry($siteConfig);
        $this->navigation = new OwasysNavigationBuilder($security);
        $this->logger = new Logger($siteRoot . '/var/logs', 'owasys-frontend.log');
        $this->profiler = new Profiler($siteRoot . '/var/profiler');
    }

    public function matchesCurrentRequest(): bool
    {
        [, $route] = $this->resolveRequest();
        return $route === 'applications/new';
    }

    public function run(): void
    {
        $this->startSession();
        [$locale, $route] = $this->resolveRequest();
        if ($route !== 'applications/new') {
            throw new RuntimeException('OWASYS_CREATION_ROUTE_MISMATCH');
        }

        $identity = $this->session->user();
        if (!is_array($identity)) {
            $this->redirect($locale, 'login');
        }
        $this->security->assertAllowed($identity, 'creation', 'open');

        $fsmConfig = $this->fsmConfig();
        $fsm = FsmSiteLoader::processorForSiteRoot($this->siteRoot);
        $state = $this->enterCreationState($fsm, $identity);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'GET') {
            $this->render($fsmConfig, $state, $locale, $identity, [], null);
            return;
        }
        if ($method !== 'POST') {
            $this->render($fsmConfig, $state, $locale, $identity, [], 'creation.error.method');
            return;
        }

        $action = trim((string) ($_POST['owasys_action'] ?? ''));
        if ($action === 'cancel-creation') {
            $transition = $fsm->transition($state, 'cancel_creation', [
                'identity' => $identity,
                'is_authenticated' => true,
            ]);
            $_SESSION[self::STATE_KEY] = (string) $transition['to_state'];
            $this->redirect($locale, 'applications');
        }
        if ($action !== 'create-application') {
            $this->render($fsmConfig, $state, $locale, $identity, [], 'creation.error.action');
            return;
        }

        $this->security->assertAllowed($identity, 'creation', 'write');
        $siteId = strtolower(trim((string) ($_POST['owasys_site_id'] ?? '')));
        $profile = strtolower(trim((string) ($_POST['owasys_profile'] ?? '')));
        $trace = $this->profiler->start();
        $traceId = $trace->getTraceId();
        $this->profiler->event('owasys.creation', 'creation.requested', [
            'profile' => $profile,
        ]);
        $this->logger->info(
            'owasys.creation',
            'creation.requested',
            ['profile' => $profile],
            $traceId
        );

        try {
            $result = $this->creation->create($siteId, $profile, $identity);
            $application = $result['application'];
            $context = [
                'identity' => $identity,
                'is_authenticated' => true,
                'roles' => is_array($identity['roles'] ?? null) ? $identity['roles'] : [],
                'selected_app' => $application,
                'app_exists' => true,
            ];
            $transition = $fsm->transition($state, 'application_created', $context);
            (new OwasysFsmActionHandlers(
                $this->session,
                $this->security,
                $this->registry
            ))->dispatcher()->dispatch($transition, $context);
            $_SESSION[self::STATE_KEY] = (string) $transition['to_state'];

            $this->profiler->event('owasys.creation', 'creation.succeeded', [
                'profile' => $profile,
            ]);
            $this->logger->info(
                'owasys.creation',
                'creation.succeeded',
                ['profile' => $profile],
                $traceId
            );
            $this->profiler->stop([
                'status' => 'succeeded',
                'workflow' => 'application_creation',
            ]);
            $this->redirect($locale, 'build');
        } catch (Throwable $error) {
            $code = $this->safeErrorCode($error);
            try {
                $transition = $fsm->transition($state, 'application_creation_failed', [
                    'identity' => $identity,
                    'is_authenticated' => true,
                ]);
                $_SESSION[self::STATE_KEY] = (string) $transition['to_state'];
            } catch (Throwable $fsmError) {
                $this->logger->error(
                    'owasys.creation',
                    'creation.failure_transition_failed',
                    ['error_code' => $this->safeErrorCode($fsmError)],
                    $traceId
                );
            }
            $this->profiler->event('owasys.creation', 'creation.failed', [
                'error_code' => $code,
                'profile' => $profile,
            ]);
            $this->logger->error(
                'owasys.creation',
                'creation.failed',
                [
                    'error_code' => $code,
                    'profile' => $profile,
                    'exception_class' => $error::class,
                    'exception_file' => $error->getFile(),
                    'exception_line' => $error->getLine(),
                ],
                $traceId
            );
            $this->profiler->stop([
                'status' => 'failed',
                'workflow' => 'application_creation',
                'error_code' => $code,
            ]);
            http_response_code(422);
            $this->render(
                $fsmConfig,
                'creation',
                $locale,
                $identity,
                [
                    'site_id' => $siteId,
                    'profile' => $profile,
                    'trace_id' => $traceId,
                    'error_code' => $code,
                ],
                $this->creationErrorKey($code)
            );
        }
    }

    /** @param array<string,mixed> $identity */
    private function enterCreationState(FsmProcessor $fsm, array $identity): string
    {
        $current = trim((string) ($_SESSION[self::STATE_KEY] ?? $fsm->initialState()));
        if (!$fsm->hasState($current)) {
            $current = $fsm->initialState();
        }
        if ($current !== 'creation') {
            $transition = $fsm->transition($current, 'open_creation', [
                'identity' => $identity,
                'is_authenticated' => true,
                'roles' => is_array($identity['roles'] ?? null) ? $identity['roles'] : [],
            ]);
            $current = (string) $transition['to_state'];
            $_SESSION[self::STATE_KEY] = $current;
        }
        return $current;
    }

    /**
     * @param array<string,mixed> $fsmConfig
     * @param array<string,mixed> $identity
     * @param array<string,string> $form
     */
    private function render(
        array $fsmConfig,
        string $state,
        string $locale,
        array $identity,
        array $form,
        ?string $errorKey
    ): void {
        $basePath = $this->basePath();
        $routeUrl = fn (string $target): string => $this->routeUrl($locale, $target);
        $profile = (string) ($form['profile'] ?? 'fullstack');
        $currentApp = $this->session->currentApp();
        $data = [
            'page' => ['title' => '', 'summary' => ''],
            'fsm' => ['state' => $state, 'module' => 'creation'],
            'identity' => [
                'authenticated' => true,
                'label' => (string) ($identity['label'] ?? ''),
                'primary_role' => (string) ($identity['roles'][0] ?? $identity['profile'] ?? ''),
            ],
            'current_app' => [
                'present' => is_array($currentApp),
                'id' => (string) ($currentApp['id'] ?? ''),
                'name' => (string) ($currentApp['name'] ?? $currentApp['id'] ?? ''),
                'kind' => (string) ($currentApp['kind'] ?? ''),
                'root' => (string) ($currentApp['root_path'] ?? ''),
            ],
            'locale' => [
                'code' => $locale,
                'name' => $this->locales->name($locale),
                'flag' => $basePath . '/asset/flags/' . rawurlencode($this->locales->flagCode($locale)) . '.svg',
            ],
            'locales' => array_map(
                fn (string $code): array => [
                    'code' => $code,
                    'name' => $this->locales->name($code),
                    'flag' => $basePath . '/asset/flags/' . rawurlencode($this->locales->flagCode($code)) . '.svg',
                    'url' => $this->routeUrl($code, 'applications/new'),
                    'active' => $code === $locale,
                ],
                $this->locales->codes()
            ),
            'assets' => [
                'score_css' => $basePath . '/asset/css/owasys.css',
                'theme_css' => $basePath . '/asset/themes/owasys/css/theme.css?v=p117q',
                'language_css' => $basePath . '/asset/css/language-switcher.css',
                'creation_css' => $basePath . '/asset/css/creation.css?v=p117u-hf9',
                'password_js' => $basePath . '/asset/js/password-visibility.js',
            ],
            'urls' => [
                'home' => $this->routeUrl($locale, 'applications'),
                'login' => $this->routeUrl($locale, 'login'),
                'logout' => $this->routeUrl($locale, 'logout'),
                'account' => $this->routeUrl($locale, 'account/password'),
                'applications' => $this->routeUrl($locale, 'applications'),
                'current' => $this->routeUrl($locale, 'applications/new'),
            ],
            'navigation' => $this->navigation->build(
                $fsmConfig,
                $identity,
                $state,
                is_array($currentApp),
                $routeUrl
            ),
            'auth' => [
                'provider' => $this->security->defaultProvider(),
                'error_required_credentials' => false,
                'error_invalid_credentials' => false,
                'error_password_mismatch' => false,
                'error_password_too_short' => false,
                'error_current_password_invalid' => false,
                'error_password_unchanged' => false,
                'error_runtime_user_missing' => false,
            ],
            'creation' => [
                'site_id' => (string) ($form['site_id'] ?? ''),
                'profile_frontend' => $profile === 'frontend',
                'profile_backend' => $profile === 'backend',
                'profile_fullstack' => !in_array($profile, ['frontend', 'backend'], true),
                'has_error' => $errorKey !== null,
                'error_site_id' => $errorKey === 'creation.error.site_id',
                'error_profile' => $errorKey === 'creation.error.profile',
                'error_exists' => $errorKey === 'creation.error.exists',
                'error_backend' => $errorKey === 'creation.error.backend',
                'error_action' => $errorKey === 'creation.error.action',
                'error_method' => $errorKey === 'creation.error.method',
                'trace_id' => (string) ($form['trace_id'] ?? ''),
                'error_code' => (string) ($form['error_code'] ?? ''),
            ],
        ];
        header('Content-Type: text/html; charset=UTF-8');
        $this->renderer->emit('creation/templates/index.score', $data);
    }

    /** @return array{0:string,1:string} */
    private function resolveRequest(): array
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $segments = trim($path, '/') === '' ? [] : explode('/', trim($path, '/'));
        if (($segments[0] ?? '') === 'owasys') {
            array_shift($segments);
        }
        $default = (string) ($this->siteConfig['default_locale'] ?? 'fr-FR');
        $negotiator = BrowserLocaleNegotiator::forLocales($this->locales->codes(), $default);
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
        $relative = trim(str_replace('\\', '/', (string) ($navigation['fsm'] ?? '')), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException('OWASYS_CREATION_FSM_PATH_INVALID');
        }
        return StructuredFileLoader::instance()->read($this->siteRoot . '/' . $relative);
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

    private function creationErrorKey(string $code): string
    {
        return match (true) {
            str_contains($code, 'SITE_ID') || str_contains($code, 'APPLICATION_ID') => 'creation.error.site_id',
            str_contains($code, 'PROFILE') => 'creation.error.profile',
            str_contains($code, 'PATH_ALREADY_EXISTS') || str_contains($code, 'ALREADY_EXISTS') => 'creation.error.exists',
            default => 'creation.error.backend',
        };
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OWASYS_CREATION_FAILED';
    }

    private function routeUrl(string $locale, string $route): string
    {
        return $this->basePath() . '/' . rawurlencode($locale) . '/' . ltrim($route, '/');
    }

    private function basePath(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $directory = str_replace('\\', '/', dirname($script));
        return in_array($directory, ['/', '.', ''], true) ? '' : rtrim($directory, '/');
    }

    private function redirect(string $locale, string $route): never
    {
        header('Location: ' . $this->routeUrl($locale, $route), true, 303);
        exit;
    }
}
