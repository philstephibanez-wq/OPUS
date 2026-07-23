<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSiteLoader;
use Opus\File\StructuredFileLoader;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Security\Sso\SsoIdentity;

final class OwasysRuntimeController
{
    private const STATE_KEY = 'opus_fsm_state_owasys';

    private readonly OwasysLocaleRegistry $locales;
    private readonly OwasysNavigationBuilder $navigation;
    private ?OwasysRegistryModel $registryModel = null;
    private ?OwasysRegistryController $registryController = null;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig,
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security,
        private readonly OwasysScorePageRenderer $renderer
    ) {
        $this->locales = new OwasysLocaleRegistry($siteConfig);
        $this->navigation = new OwasysNavigationBuilder($security);
    }

    public function run(): void
    {
        $this->startSession();

        [$locale, $routeKey] = $this->resolveRequest();

        $fsmConfig = $this->loadFsmConfig();
        $fsm = FsmSiteLoader::processorForSiteRoot($this->siteRoot);
        $currentState = $this->currentState($fsm);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $identity = $this->session->user();
        $requestResult = null;
        $errorKey = null;

        try {
            $resolved = $this->resolveEvent(
                $method,
                $routeKey,
                $currentState,
                $identity
            );
        } catch (Throwable $error) {
            if (str_starts_with($error->getMessage(), 'OPUS_ACL_DENIED:')) {
                $this->fail(403, $error->getMessage());
            }
            $this->fail(400, 'OWASYS_REQUEST_REJECTED:' . $error->getMessage());
        }
        $event = (string) ($resolved['event'] ?? '');
        $eventContext = is_array($resolved['context'] ?? null)
            ? $resolved['context']
            : [];
        $requestResult = is_array($resolved['result'] ?? null)
            ? $resolved['result']
            : null;
        $errorKey = is_string($resolved['error'] ?? null)
            ? $resolved['error']
            : null;
        $redirectAfterTransition = ($resolved['redirect'] ?? false) === true;

        $context = array_replace(
            $this->fsmContext($identity),
            $eventContext,
            [
                'identity' => $identity,
                'post' => $_POST,
                'route' => $routeKey,
                'method' => $method,
            ]
        );

        $targetState = $currentState;

        if ($event !== '') {
            try {
                $transition = $fsm->transition($currentState, $event, $context);
                $targetState = (string) ($transition['to_state'] ?? '');
                $this->assertTargetStateAccess(
                    $fsm,
                    $targetState,
                    $context
                );

                $this->actionHandlersFor($transition)->dispatcher()->dispatch($transition, $context);
                $_SESSION[self::STATE_KEY] = $targetState;
            } catch (Throwable $error) {
                $handled = $this->handleTransitionFailure(
                    $error,
                    $fsm,
                    $currentState,
                    $locale,
                    $context
                );

                if ($handled['redirect'] === true) {
                    $this->redirect($locale, (string) $handled['route']);
                }

                $targetState = (string) $handled['state'];
                $errorKey = (string) $handled['error'];
            }
        }

        if ($redirectAfterTransition) {
            $state = $fsm->state($targetState);
            $this->redirect($locale, (string) ($state['route'] ?? 'login'));
        }

        $this->renderState(
            $fsm,
            $fsmConfig,
            $targetState,
            $locale,
            $routeKey,
            $requestResult,
            $errorKey
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
            $this->fail(500, 'OWASYS_SESSION_NAME_INVALID');
        }

        session_name($name);
        session_start();
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

        $defaultLocale = (string) (
            $this->siteConfig['default_locale'] ?? 'fr-FR'
        );
        $negotiator = BrowserLocaleNegotiator::forLocales(
            $this->locales->codes(),
            $defaultLocale
        );
        $first = (string) ($segments[0] ?? '');
        $explicit = $this->locales->resolveExplicit($first);

        if (is_string($explicit)) {
            $locale = $explicit;
            array_shift($segments);
        } elseif (
            $first !== ''
            && preg_match(
                '/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})+$/',
                $first
            ) === 1
        ) {
            $this->fail(404, 'OWASYS_LOCALE_UNSUPPORTED:' . $first);
        } else {
            $locale = $negotiator->negotiate(
                is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null)
                    ? $_SERVER['HTTP_ACCEPT_LANGUAGE']
                    : null
            )->value;
        }

        $routeKey = implode('/', $segments);

        return [$locale, $routeKey === '' ? 'login' : $routeKey];
    }

    /**
     * @param array<string,mixed>|null $identity
     * @return array<string,mixed>
     */
    private function resolveEvent(
        string $method,
        string $routeKey,
        string $currentState,
        ?array $identity
    ): array {
        if (
            is_array($identity)
            && ($identity['must_change_password'] ?? false) === true
            && $routeKey !== 'account/password'
            && $routeKey !== 'logout'
        ) {
            return ['event' => 'open_account', 'redirect' => true];
        }

        if ($method === 'POST') {
            $action = trim((string) ($_POST['owasys_action'] ?? ''));

            if ($routeKey === 'login' && $action === 'sso-authenticate') {
                try {
                    $pending = $this->security->authenticate($_POST);

                    return [
                        'event' => $pending->mustChangePassword
                            ? 'password_change_required'
                            : 'login_success',
                        'context' => [
                            'pending_identity' => $pending,
                            'must_change_password' => $pending->mustChangePassword,
                        ],
                        'redirect' => true,
                    ];
                } catch (Throwable) {
                    return [
                        'event' => 'login_failed',
                        'error' => 'auth.error.invalid_credentials',
                        'redirect' => false,
                    ];
                }
            }

            if ($routeKey === 'account/password' && $action === 'change-password') {
                if (!is_array($identity)) {
                    return ['event' => 'auth_required', 'redirect' => true];
                }

                $this->security->assertAllowed($identity, 'account', 'change');

                if (
                    (string) ($_POST['owasys_current_password'] ?? '') === ''
                    || (string) ($_POST['owasys_new_password'] ?? '') === ''
                    || (string) ($_POST['owasys_confirm_password'] ?? '') === ''
                ) {
                    return [
                        'event' => 'password_change_failed',
                        'error' => 'auth.error.required_credentials',
                    ];
                }

                if (
                    (string) ($_POST['owasys_new_password'] ?? '')
                    !== (string) ($_POST['owasys_confirm_password'] ?? '')
                ) {
                    return [
                        'event' => 'password_change_failed',
                        'error' => 'auth.error.password_mismatch',
                    ];
                }

                return [
                    'event' => 'password_changed',
                    'redirect' => true,
                ];
            }

            if ($routeKey === 'applications') {
                if (!is_array($identity)) {
                    return ['event' => 'auth_required', 'redirect' => true];
                }

                $this->security->assertAllowed($identity, 'registry', 'write');
                $result = $this->registryController()->handle($method, $_POST);

                return [
                    'event' => (string) ($result['event'] ?? 'registry_action_failed'),
                    'context' => [
                        'selected_app' => is_array($result['selected_app'] ?? null)
                            ? $result['selected_app']
                            : null,
                        'app_exists' => is_array($result['selected_app'] ?? null),
                        'registry_entry' => $result['selected_app'] ?? null,
                    ],
                    'result' => $result,
                    'error' => is_string($result['error'] ?? null)
                        ? $result['error']
                        : null,
                    'redirect' => ($result['error'] ?? null) === null,
                ];
            }

            $this->fail(400, 'OWASYS_POST_ACTION_INVALID:' . $routeKey . ':' . $action);
        }

        if ($method !== 'GET') {
            $this->fail(405, 'OWASYS_HTTP_METHOD_NOT_ALLOWED');
        }

        if ($routeKey === 'login') {
            return $identity === null
                ? ['event' => 'open_login']
                : ['event' => 'change_app', 'redirect' => true];
        }

        if ($routeKey === 'account/password') {
            return ['event' => 'open_account'];
        }

        $signal = $this->resolveSignal($routeKey);
        if ($signal === '') {
            $this->fail(404, 'OWASYS_ROUTE_NOT_FOUND:' . $routeKey);
        }

        return [
            'event' => $signal,
            'redirect' => $signal === 'logout',
        ];
    }

    private function currentState(FsmProcessor $fsm): string
    {
        $current = trim((string) ($_SESSION[self::STATE_KEY] ?? $fsm->initialState()));
        if (!$fsm->hasState($current)) {
            $current = $fsm->initialState();
        }

        if (!$this->session->isAuthenticated() && $current !== $fsm->initialState()) {
            $current = $fsm->initialState();
            $_SESSION[self::STATE_KEY] = $current;
        }

        return $current;
    }

    /** @param array<string,mixed>|null $identity @return array<string,mixed> */
    private function fsmContext(?array $identity): array
    {
        $currentApp = $this->session->currentApp();

        return [
            'identity' => $identity,
            'is_authenticated' => is_array($identity),
            'roles' => is_array($identity['roles'] ?? null) ? $identity['roles'] : [],
            'current_app' => $currentApp,
            'has_current_app' => is_array($currentApp),
        ];
    }

    /** @param array<string,mixed> $context */
    private function assertTargetStateAccess(
        FsmProcessor $fsm,
        string $targetState,
        array $context
    ): void {
        $state = $fsm->state($targetState);
        $pending = $context['pending_identity'] ?? null;
        $identity = $pending instanceof SsoIdentity
            ? $pending->toSession()
            : (is_array($context['identity'] ?? null) ? $context['identity'] : null);

        if (($state['requires_auth'] ?? false) === true && !is_array($identity)) {
            throw new RuntimeException('OWASYS_AUTH_REQUIRED');
        }

        $hasCurrent = is_array($this->session->currentApp())
            || is_array($context['selected_app'] ?? null);
        if (($state['requires_current_app'] ?? false) === true && !$hasCurrent) {
            throw new RuntimeException('OWASYS_CURRENT_APP_REQUIRED');
        }

        if (($state['requires_auth'] ?? false) === true) {
            $this->security->assertAllowed(
                $identity,
                (string) ($state['module'] ?? $targetState),
                'open'
            );
        }
    }

    /**
     * @param array<string,mixed> $context
     * @return array{state:string,error:string,redirect:bool,route:string}
     */
    private function handleTransitionFailure(
        Throwable $error,
        FsmProcessor $fsm,
        string $currentState,
        string $locale,
        array $context
    ): array {
        $message = $error->getMessage();

        if ($message === 'OWASYS_AUTH_REQUIRED') {
            $transition = $fsm->transition($currentState, 'auth_required', $context);
            $this->actionHandlersFor($transition)->dispatcher()->dispatch($transition, $context);
            $state = (string) $transition['to_state'];
            $_SESSION[self::STATE_KEY] = $state;

            return [
                'state' => $state,
                'error' => '',
                'redirect' => true,
                'route' => (string) ($fsm->state($state)['route'] ?? 'login'),
            ];
        }

        if (
            $message === 'OWASYS_CURRENT_APP_REQUIRED'
            || str_contains($message, 'OPUS_FSM_GUARD_FAILED: current_app_required')
        ) {
            $transition = $fsm->transition($currentState, 'change_app', $context);
            $this->assertTargetStateAccess($fsm, (string) $transition['to_state'], $context);
            $state = (string) $transition['to_state'];
            $_SESSION[self::STATE_KEY] = $state;

            return [
                'state' => $state,
                'error' => '',
                'redirect' => true,
                'route' => (string) ($fsm->state($state)['route'] ?? 'applications'),
            ];
        }

        $passwordError = $this->passwordErrorKey($message);
        if ($passwordError !== null && $currentState === 'account') {
            $failure = $fsm->transition($currentState, 'password_change_failed', $context);
            $state = (string) $failure['to_state'];
            $_SESSION[self::STATE_KEY] = $state;

            return [
                'state' => $state,
                'error' => $passwordError,
                'redirect' => false,
                'route' => '',
            ];
        }

        if (str_starts_with($message, 'OPUS_ACL_DENIED:')) {
            $this->fail(403, $message);
        }

        $this->fail(409, 'OWASYS_FSM_RUNTIME_REJECTED:' . $message);
    }

    private function passwordErrorKey(string $message): ?string
    {
        return match ($message) {
            'OWASYS_PASSWORD_CONFIRMATION_MISMATCH' => 'auth.error.password_mismatch',
            'OPUS_SSO_CURRENT_PASSWORD_INVALID' => 'auth.error.current_password_invalid',
            'OPUS_SSO_NEW_PASSWORD_TOO_SHORT' => 'auth.error.password_too_short',
            'OPUS_SSO_PASSWORD_UNCHANGED' => 'auth.error.password_unchanged',
            'OPUS_SSO_SUBJECT_UNKNOWN' => 'auth.error.runtime_user_missing',
            default => null,
        };
    }

    /**
     * @param array<string,mixed>|null $requestResult
     */
    private function renderState(
        FsmProcessor $fsm,
        array $fsmConfig,
        string $stateId,
        string $locale,
        string $requestRoute,
        ?array $requestResult,
        ?string $errorKey
    ): void {
        $state = $fsm->state($stateId);
        $module = (string) ($state['module'] ?? $stateId);
        $route = (string) ($state['route'] ?? $requestRoute);
        $identity = $this->session->user();
        $currentApp = $this->session->currentApp();
        $basePath = $this->basePath();
        $routeUrl = fn (string $targetRoute): string => $this->routeUrl(
            $locale,
            $targetRoute
        );

        $data = [
            'page' => [
                'title' => '',
                'summary' => '',
            ],
            'fsm' => [
                'state' => $stateId,
                'module' => $module,
            ],
            'identity' => [
                'authenticated' => is_array($identity),
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
                    'url' => $this->routeUrl($code, $route),
                    'active' => $code === $locale,
                ],
                $this->locales->codes()
            ),
            'assets' => [
                'score_css' => $basePath . '/asset/css/owasys.css',
                'theme_css' => $basePath . '/asset/themes/owasys/css/theme.css?v=p117o-r1',
                'language_css' => $basePath . '/asset/css/language-switcher.css',
                'password_js' => $basePath . '/asset/js/password-visibility.js',
            ],
            'urls' => [
                'home' => $this->routeUrl($locale, is_array($identity) ? 'applications' : 'login'),
                'login' => $this->routeUrl($locale, 'login'),
                'logout' => $this->routeUrl($locale, 'logout'),
                'account' => $this->routeUrl($locale, 'account/password'),
                'applications' => $this->routeUrl($locale, 'applications'),
                'current' => $this->routeUrl($locale, $route),
            ],
            'navigation' => $this->navigation->build(
                $fsmConfig,
                $identity,
                $stateId,
                is_array($currentApp),
                $routeUrl
            ),
            'auth' => [
                'provider' => $this->security->defaultProvider(),
                'error_required_credentials' => $errorKey === 'auth.error.required_credentials',
                'error_invalid_credentials' => $errorKey === 'auth.error.invalid_credentials',
                'error_password_mismatch' => $errorKey === 'auth.error.password_mismatch',
                'error_password_too_short' => $errorKey === 'auth.error.password_too_short',
                'error_current_password_invalid' => $errorKey === 'auth.error.current_password_invalid',
                'error_password_unchanged' => $errorKey === 'auth.error.password_unchanged',
                'error_runtime_user_missing' => $errorKey === 'auth.error.runtime_user_missing',
            ],
        ];

        $template = $module . '/templates/index.score';

        if ($module === 'registry') {
            $result = $requestResult ?? $this->registryController()->handle('GET', []);
            $data = array_replace_recursive(
                $data,
                $this->registryViewData($result, $currentApp)
            );
        }

        if (!is_file($this->siteRoot . '/application/' . $template)) {
            $template = 'default/templates/pending.score';
            http_response_code(501);
        }

        header('Content-Type: text/html; charset=UTF-8');
        $this->renderer->emit($template, $data);
    }

    /**
     * @param array<string,mixed> $result
     * @param array<string,mixed>|null $currentApp
     * @return array<string,mixed>
     */
    private function registryViewData(
        array $result,
        ?array $currentApp
    ): array {
        $entries = [];
        foreach ((array) ($result['entries'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryId = (string) ($entry['id'] ?? '');
            $isCurrent = is_array($currentApp)
                && (string) ($currentApp['id'] ?? '') === $entryId;

            $entries[] = [
                'id' => $entryId,
                'name' => (string) ($entry['name'] ?? $entryId),
                'root' => (string) ($entry['root_path'] ?? ''),
                'kind' => (string) ($entry['kind'] ?? ''),
                'role' => (string) ($entry['role'] ?? ''),
                'locale' => (string) ($entry['default_locale'] ?? ''),
                'theme' => (string) ($entry['theme'] ?? ''),
                'status' => (string) ($entry['status'] ?? ''),
                'current' => $isCurrent,
            ];
        }

        $events = [];
        foreach ((array) ($result['recent_events'] ?? []) as $event) {
            if (!is_array($event)) {
                continue;
            }
            $events[] = [
                'type' => (string) ($event['event_type'] ?? ''),
                'application' => (string) ($event['application_id'] ?? ''),
                'created_at' => (string) ($event['created_at'] ?? ''),
            ];
        }

        $sync = is_array($result['sync'] ?? null) ? $result['sync'] : [];

        return [
            'registry' => [
                'empty' => $entries === [],
                'events_empty' => $events === [],
                'error_application_required' => ($result['error'] ?? null) === 'registry.error.application_required',
                'error_application_not_found' => ($result['error'] ?? null) === 'registry.error.application_not_found',
                'error_action_invalid' => ($result['error'] ?? null) === 'registry.error.action_invalid',
            ],
            'entries' => $entries,
            'events' => $events,
            'sync' => [
                'database' => (string) ($sync['database'] ?? ''),
                'total' => (string) ($sync['total'] ?? 0),
                'seed_imported' => (string) ($sync['seed_imported'] ?? 0),
                'discovered_imported' => (string) ($sync['discovered_imported'] ?? 0),
            ],
        ];
    }


    /** @param array<string,mixed> $transition */
    private function actionHandlersFor(array $transition): OwasysFsmActionHandlers
    {
        $actions = is_array($transition['actions'] ?? null) ? $transition['actions'] : [];
        $requiresRegistry = array_intersect(
            $actions,
            ['set_current_app', 'start_creation_flow']
        ) !== [] || (
            in_array('clear_current_app', $actions, true)
            && is_array($this->session->currentApp())
        );

        return new OwasysFsmActionHandlers(
            $this->session,
            $this->security,
            $requiresRegistry ? $this->registryModel() : null
        );
    }

    private function registryModel(): OwasysRegistryModel
    {
        if (!$this->registryModel instanceof OwasysRegistryModel) {
            $this->registryModel = new OwasysRegistryModel(
                $this->siteRoot,
                dirname(dirname($this->siteRoot))
            );
        }

        return $this->registryModel;
    }

    private function registryController(): OwasysRegistryController
    {
        if (!$this->registryController instanceof OwasysRegistryController) {
            $this->registryController = new OwasysRegistryController($this->registryModel());
        }

        return $this->registryController;
    }

    private function resolveSignal(string $routeKey): string
    {
        $routes = $this->readJson(
            $this->siteRoot . '/config/routes.json',
            'OWASYS_ROUTES_CONFIG_INVALID'
        );

        if (
            (string) ($routes['contract'] ?? '')
            !== 'OPUS_SIGNAL_ROUTES_V2'
        ) {
            throw new RuntimeException(
                'OWASYS_ROUTES_CONTRACT_INVALID'
            );
        }

        $systemRoutes = is_array($routes['system_routes'] ?? null)
            ? $routes['system_routes']
            : [];

        if (is_string($systemRoutes[$routeKey] ?? null)) {
            return trim((string) $systemRoutes[$routeKey]);
        }

        $applicationRoutes = is_array($routes['routes'] ?? null)
            ? $routes['routes']
            : [];

        return is_string($applicationRoutes[$routeKey] ?? null)
            ? trim((string) $applicationRoutes[$routeKey])
            : '';
    }

    /** @return array<string,mixed> */
    private function loadFsmConfig(): array
    {
        $navigation = is_array($this->siteConfig['navigation'] ?? null)
            ? $this->siteConfig['navigation']
            : [];
        $relative = trim(str_replace('\\', '/', (string) ($navigation['fsm'] ?? '')), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException('OWASYS_FSM_CONFIG_PATH_INVALID');
        }

        return $this->readJson(
            $this->siteRoot . '/' . $relative,
            'OWASYS_FSM_CONFIG_INVALID'
        );
    }

    /** @return array<string,string> */

    /** @return array<string,mixed> */
    private function readJson(string $path, string $error): array
    {
        try {
            return StructuredFileLoader::instance()->read($path);
        } catch (Throwable $cause) {
            throw new RuntimeException(
                $error . ':' . $path . ':' . $cause->getMessage(),
                0,
                $cause
            );
        }
    }

    private function routeUrl(string $locale, string $route): string
    {
        return $this->basePath()
            . '/'
            . rawurlencode($locale)
            . '/'
            . ltrim($route, '/');
    }

    private function basePath(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
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

    private function fail(int $status, string $message): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        exit($message);
    }
}
