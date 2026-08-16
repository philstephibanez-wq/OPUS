<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\File\StructuredFileLoader;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Http\LocalizedRouteResolverInterface;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Sso\SsoIdentity;

final class OwasysRuntimeController
{
    private const FSM_SESSION_KEY = 'opus.fsm.owasys-front';

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
        private readonly OwasysScorePageRenderer $renderer,
        private readonly LocalizedRouteResolverInterface $localizedRoutes,
        private readonly OwasysSessionRuntimeInterface $sessionRuntime,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null
    ) {
        $this->locales = new OwasysLocaleRegistry($siteConfig);
        $this->navigation = new OwasysNavigationBuilder($this->siteRoot, $security);
    }

    public function run(): void
    {
        $this->sessionRuntime->start();

        [$locale, $routeKey] = $this->resolveRequest();

        $fsmConfig = $this->loadFsmConfig();
        $fsm = FsmSiteLoader::processorForSiteRoot(
            $this->siteRoot,
            [],
            $this->profiler,
            $this->parentSpanId
        );
        $fsmStore = new FsmSessionStore(self::FSM_SESSION_KEY);
        $fsmStore->restore($fsm);
        $currentState = $this->currentState($fsm);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $identity = $this->session->user();
        $requestResult = null;
        $errorKey = null;

        try {
            $resolved = $this->resolveRequestSignal(
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
        $signal = (string) ($resolved['signal'] ?? '');
        $signalContext = is_array($resolved['context'] ?? null)
            ? $resolved['context']
            : [];
        $requestResult = is_array($resolved['result'] ?? null)
            ? $resolved['result']
            : null;
        $errorKey = is_string($resolved['error'] ?? null)
            ? $resolved['error']
            : null;
        $redirectAfterTransition = ($resolved['redirect'] ?? false) === true;
        $externalRedirect = is_string(
            $resolved['external_redirect'] ?? null
        )
            ? trim((string) $resolved['external_redirect'])
            : '';

        $context = array_replace(
            $this->fsmContext($identity),
            $signalContext,
            [
                'identity' => $identity,
                'post' => $_POST,
                'route' => $routeKey,
                'method' => $method,
            ]
        );

        $targetState = $currentState;

        if ($signal !== '') {
            try {
                $transition = $fsm->transition($currentState, $signal, $context);
                $targetState = (string) ($transition['next_state'] ?? '');
                $this->assertTargetStateAccess(
                    $fsm,
                    $targetState,
                    $context
                );

                $this->actionHandlersFor($transition)
                    ->dispatcher()
                    ->dispatch($transition, $context);
                $fsmStore->persist($fsm);
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

        if ($externalRedirect !== '') {
            $this->redirectExternal($externalRedirect);
        }

        if ($redirectAfterTransition) {
            $state = $fsm->state($targetState);
            $this->redirect(
                $locale,
                (string) ($state['route'] ?? 'login'),
                $signal === 'create_new_app'
                    ? ['owasys_new' => '1']
                    : []
            );
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

        $publicRoute = implode('/', $segments);
        $routeKey = $publicRoute === ''
            ? 'login'
            : $this->localizedRoutes->resolve(
                $locale,
                $publicRoute
            );

        return [$locale, $routeKey];
    }

    /**
     * @param array<string,mixed>|null $identity
     * @return array<string,mixed>
     */
    private function resolveRequestSignal(
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
            return [
                'signal' => 'open_password_change',
                'redirect' => true,
            ];
        }

        if ($method === 'POST') {
            $action = trim((string) ($_POST['owasys_action'] ?? ''));

            if ($routeKey === 'login' && $action === 'sso-authenticate') {
                try {
                    $pending = $this->security->authenticate($_POST);

                    return [
                        'signal' => $pending->mustChangePassword
                            ? 'password_change_required'
                            : 'login_success',
                        'context' => [
                            'pending_identity' => $pending,
                            'must_change_password' =>
                                $pending->mustChangePassword,
                        ],
                        'redirect' => true,
                    ];
                } catch (Throwable) {
                    return [
                        'signal' => 'login_failed',
                        'error' => 'auth.error.invalid_credentials',
                        'redirect' => false,
                    ];
                }
            }

            if (
                $routeKey === 'account/password'
                && $action === 'change-password'
            ) {
                if (!is_array($identity)) {
                    return [
                        'signal' => 'auth_required',
                        'redirect' => true,
                    ];
                }

                $this->security->assertAllowed(
                    $identity,
                    'account',
                    'change'
                );

                if (
                    (string) ($_POST['owasys_current_password'] ?? '') === ''
                    || (string) ($_POST['owasys_new_password'] ?? '') === ''
                    || (string) ($_POST['owasys_confirm_password'] ?? '') === ''
                ) {
                    return [
                        'signal' => 'password_change_failed',
                        'error' => 'auth.error.required_credentials',
                    ];
                }

                if (
                    (string) ($_POST['owasys_new_password'] ?? '')
                    !== (string) ($_POST['owasys_confirm_password'] ?? '')
                ) {
                    return [
                        'signal' => 'password_change_failed',
                        'error' => 'auth.error.password_mismatch',
                    ];
                }

                return [
                    'signal' => 'password_changed',
                    'redirect' => true,
                ];
            }

            if ($routeKey === 'applications') {
                if (!is_array($identity)) {
                    return [
                        'signal' => 'auth_required',
                        'redirect' => true,
                    ];
                }

                $this->assertRegistryActionAllowed($identity, $action);
                $result = $this->registryController()->handle(
                    $method,
                    $_POST
                );

                return [
                    'signal' => (string) (
                        $result['signal'] ?? 'registry_action_failed'
                    ),
                    'context' => [
                        'selected_app' => is_array(
                            $result['selected_app'] ?? null
                        )
                            ? $result['selected_app']
                            : null,
                        'app_exists' => is_array(
                            $result['selected_app'] ?? null
                        ),
                        'registry_entry' =>
                            $result['selected_app'] ?? null,
                    ],
                    'result' => $result,
                    'error' => is_string($result['error'] ?? null)
                        ? $result['error']
                        : null,
                    'redirect' => ($result['error'] ?? null) === null,
                ];
            }

            if (
                $routeKey === 'build'
                && $action === 'start-development-server'
            ) {
                if (!is_array($identity)) {
                    return [
                        'signal' => 'auth_required',
                        'redirect' => true,
                    ];
                }
                $currentApp = $this->session->currentApp();
                if (!is_array($currentApp)) {
                    throw new RuntimeException(
                        'OWASYS_CURRENT_APP_REQUIRED'
                    );
                }
                if (
                    strtolower((string) ($currentApp['kind'] ?? ''))
                    === 'backend'
                ) {
                    throw new RuntimeException(
                        'OWASYS_DEV_PREVIEW_BACKEND_ONLY'
                    );
                }
                $this->security->assertAllowed(
                    $identity,
                    'build',
                    'preview'
                );
                $result = $this->security->startDevelopmentServer(
                    $identity,
                    (string) ($currentApp['id'] ?? '')
                );

                return [
                    'signal' => 'open_build',
                    'result' => $result,
                    'external_redirect' => (string) (
                        $result['url'] ?? ''
                    ),
                    'redirect' => false,
                ];
            }

            $this->fail(
                400,
                'OWASYS_POST_ACTION_INVALID:'
                    . $routeKey
                    . ':'
                    . $action
            );
        }

        if ($method !== 'GET') {
            $this->fail(405, 'OWASYS_HTTP_METHOD_NOT_ALLOWED');
        }

        if ($routeKey === 'login') {
            return $identity === null
                ? ['signal' => 'open_login']
                : ['signal' => 'change_app', 'redirect' => true];
        }

        $signal = $this->resolveSignal($routeKey);
        if ($signal === '') {
            $this->fail(
                404,
                'OWASYS_ROUTE_NOT_FOUND:' . $routeKey
            );
        }

        return [
            'signal' => $signal,
            'redirect' => $signal === 'logout',
        ];
    }

    private function currentState(FsmProcessor $fsm): string
    {
        $current = $fsm->currentState();

        if (
            !$this->session->isAuthenticated()
            && $current !== $fsm->initialState()
        ) {
            $fsm->reset();
            $current = $fsm->currentState();
        }

        return $current;
    }

    /**
     * @param array<string,mixed>|null $identity
     * @return array<string,mixed>
     */
    private function fsmContext(?array $identity): array
    {
        $currentApp = $this->session->currentApp();

        return [
            'identity' => $identity,
            'is_authenticated' => is_array($identity),
            'roles' => is_array($identity['roles'] ?? null)
                ? $identity['roles']
                : [],
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
            : (
                is_array($context['identity'] ?? null)
                    ? $context['identity']
                    : null
            );

        if (
            ($state['requires_auth'] ?? false) === true
            && !is_array($identity)
        ) {
            throw new RuntimeException('OWASYS_AUTH_REQUIRED');
        }

        $hasCurrent = is_array($this->session->currentApp())
            || is_array($context['selected_app'] ?? null);
        if (
            ($state['requires_current_app'] ?? false) === true
            && !$hasCurrent
        ) {
            throw new RuntimeException(
                'OWASYS_CURRENT_APP_REQUIRED'
            );
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
            $transition = $fsm->transition(
                $currentState,
                'auth_required',
                $context
            );
            $this->actionHandlersFor($transition)
                ->dispatcher()
                ->dispatch($transition, $context);
            $state = (string) $transition['next_state'];
            (new FsmSessionStore(self::FSM_SESSION_KEY))->persist($fsm);

            return [
                'state' => $state,
                'error' => '',
                'redirect' => true,
                'route' => (string) (
                    $fsm->state($state)['route'] ?? 'login'
                ),
            ];
        }

        if (
            $message === 'OWASYS_CURRENT_APP_REQUIRED'
            || str_contains(
                $message,
                'OPUS_FSM_GUARD_FAILED: current_app_required'
            )
        ) {
            $transition = $fsm->transition(
                $currentState,
                'change_app',
                $context
            );
            $this->assertTargetStateAccess(
                $fsm,
                (string) $transition['next_state'],
                $context
            );
            $state = (string) $transition['next_state'];
            (new FsmSessionStore(self::FSM_SESSION_KEY))->persist($fsm);

            return [
                'state' => $state,
                'error' => '',
                'redirect' => true,
                'route' => (string) (
                    $fsm->state($state)['route'] ?? 'applications'
                ),
            ];
        }

        $passwordError = $this->passwordErrorKey($message);
        if (
            $passwordError !== null
            && $currentState === 'password'
        ) {
            $failure = $fsm->transition(
                $currentState,
                'password_change_failed',
                $context
            );
            $state = (string) $failure['next_state'];
            (new FsmSessionStore(self::FSM_SESSION_KEY))->persist($fsm);

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

        if (str_starts_with($message, 'OPUS_FSM_')) {
            $this->fail(
                409,
                'OWASYS_FSM_RUNTIME_REJECTED:' . $message
            );
        }

        $this->fail(409, $message);
    }

    /** @param array<string,mixed> $identity */
    private function assertRegistryActionAllowed(
        array $identity,
        string $action
    ): void {
        [$resource, $permission] = match ($action) {
            'select-app', 'clear-app-context' => ['registry', 'select'],
            'create-new-app' => ['creation', 'open'],
            'delete-app' => ['registry', 'delete'],
            default => throw new RuntimeException(
                'OWASYS_REGISTRY_ACTION_INVALID:' . $action
            ),
        };
        $this->security->assertAllowed(
            $identity,
            $resource,
            $permission
        );
    }

    private function passwordErrorKey(string $message): ?string
    {
        return match ($message) {
            'OWASYS_PASSWORD_CONFIRMATION_MISMATCH' =>
                'auth.error.password_mismatch',
            'OPUS_SSO_CURRENT_PASSWORD_INVALID' =>
                'auth.error.current_password_invalid',
            'OPUS_SSO_NEW_PASSWORD_TOO_SHORT' =>
                'auth.error.password_too_short',
            'OPUS_SSO_PASSWORD_UNCHANGED' =>
                'auth.error.password_unchanged',
            'OPUS_SSO_SUBJECT_UNKNOWN' =>
                'auth.error.runtime_user_missing',
            default => null,
        };
    }

    /** @param array<string,mixed>|null $requestResult */
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
        $registryResult = null;

        if ($module === 'registry') {
            $registryResult = $requestResult
                ?? $this->registryController()->handle('GET', []);
            $canonicalCurrent = $this->registryModel()
                ->canonicalCurrent($currentApp);

            if (
                is_array($currentApp)
                && !is_array($canonicalCurrent)
            ) {
                $this->session->clearCurrentApp();
                $currentApp = null;
            } elseif (is_array($canonicalCurrent)) {
                $this->session->setCurrentApp($canonicalCurrent);
                $currentApp = $canonicalCurrent;
            }
        }

        $basePath = $this->basePath();
        $routeUrl = fn (string $targetRoute): string =>
            $this->routeUrl($locale, $targetRoute);

        $canChangePassword = is_array($identity)
            && (string) ($identity['provider'] ?? '') === 'local-password'
            && $this->security->isAllowed(
                $identity,
                'account',
                'change'
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
                'primary_role' => (string) (
                    $identity['roles'][0]
                    ?? $identity['profile']
                    ?? ''
                ),
            ],
            'current_app' => [
                'present' => is_array($currentApp),
                'id' => (string) ($currentApp['id'] ?? ''),
                'name' => (string) (
                    $currentApp['name']
                    ?? $currentApp['id']
                    ?? ''
                ),
                'kind' => (string) ($currentApp['kind'] ?? ''),
                'root' => (string) (
                    $currentApp['root_path'] ?? ''
                ),
            ],
            'locale' => [
                'code' => $locale,
                'name' => $this->locales->name($locale),
                'flag' => $basePath
                    . '/asset/flags/'
                    . rawurlencode(
                        $this->locales->flagCode($locale)
                    )
                    . '.svg',
            ],
            'locales' => array_map(
                fn (string $code): array => [
                    'code' => $code,
                    'name' => $this->locales->name($code),
                    'flag' => $basePath
                        . '/asset/flags/'
                        . rawurlencode(
                            $this->locales->flagCode($code)
                        )
                        . '.svg',
                    'url' => $this->routeUrl($code, $route),
                    'active' => $code === $locale,
                ],
                $this->locales->codes()
            ),
            'assets' => [
                'score_css' =>
                    $basePath . '/asset/css/owasys.css',
                'theme_css' => $basePath
                    . '/asset/themes/owasys/css/theme.css?v=p117q',
                'language_css' => $basePath
                    . '/asset/css/language-switcher.css',
                'password_js' => $basePath
                    . '/asset/js/password-visibility.js',
            ],
            'urls' => [
                'home' => $this->routeUrl(
                    $locale,
                    is_array($identity)
                        ? 'applications'
                        : 'login'
                ),
                'login' => $this->routeUrl($locale, 'login'),
                'logout' => $this->routeUrl($locale, 'logout'),
                'account' => $this->routeUrl($locale, 'account'),
                'password' => $this->routeUrl(
                    $locale,
                    'account/password'
                ),
                'applications' => $this->routeUrl(
                    $locale,
                    'applications'
                ),
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
                'can_change_password' => $canChangePassword,
                'cannot_change_password' => !$canChangePassword,
                'error_required_credentials' =>
                    $errorKey === 'auth.error.required_credentials',
                'error_invalid_credentials' =>
                    $errorKey === 'auth.error.invalid_credentials',
                'error_password_mismatch' =>
                    $errorKey === 'auth.error.password_mismatch',
                'error_password_too_short' =>
                    $errorKey === 'auth.error.password_too_short',
                'error_current_password_invalid' =>
                    $errorKey === 'auth.error.current_password_invalid',
                'error_password_unchanged' =>
                    $errorKey === 'auth.error.password_unchanged',
                'error_runtime_user_missing' =>
                    $errorKey === 'auth.error.runtime_user_missing',
            ],
        ];

        $templateName = trim((string) (
            $state['template'] ?? 'index.score'
        ));
        if (
            $templateName === ''
            || str_contains($templateName, '/')
            || str_contains($templateName, '\\')
            || str_contains($templateName, '..')
            || preg_match(
                '/^[A-Za-z0-9._-]+\.score$/D',
                $templateName
            ) !== 1
        ) {
            throw new RuntimeException(
                'OWASYS_STATE_TEMPLATE_INVALID:'
                    . $stateId
                    . ':'
                    . $templateName
            );
        }
        $template = $module . '/templates/' . $templateName;

        if ($module === 'registry') {
            $data = array_replace_recursive(
                $data,
                $this->registryViewData(
                    is_array($registryResult)
                        ? $registryResult
                        : [],
                    $currentApp,
                    $identity
                )
            );
        }

        if ($module === 'build') {
            $kind = strtolower((string) ($currentApp['kind'] ?? ''));
            $previewResult = is_array($requestResult)
                && ($requestResult['contract'] ?? null)
                    === 'OPUS_CONSOLE_DEV_SERVER_START_RESULT_V1'
                ? $requestResult
                : null;
            $data['build'] = [
                'can_preview' => is_array($identity)
                    && $kind !== 'backend'
                    && $this->security->isAllowed(
                        $identity,
                        'build',
                        'preview'
                    ),
                'preview_started' => is_array($previewResult)
                    && ($previewResult['started'] ?? false) === true,
                'preview_url' => is_array($previewResult)
                    ? (string) ($previewResult['url'] ?? '')
                    : '',
                'preview_failed' =>
                    $errorKey === 'build.preview_failed',
            ];
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
        ?array $currentApp,
        ?array $identity
    ): array {
        $entries = [];
        foreach ((array) ($result['entries'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryId = (string) ($entry['id'] ?? '');
            $isCurrent = is_array($currentApp)
                && (string) ($currentApp['id'] ?? '') === $entryId;

            $singleton = is_array($entry['singleton'] ?? null)
                ? $entry['singleton']
                : [];
            $singletonCompliant =
                ($singleton['compliant'] ?? false) === true;

            $entries[] = [
                'id' => $entryId,
                'name' => (string) (
                    $entry['name'] ?? $entryId
                ),
                'root' => (string) (
                    $entry['root_path'] ?? ''
                ),
                'kind' => (string) ($entry['kind'] ?? ''),
                'role' => (string) ($entry['role'] ?? ''),
                'locale' => (string) (
                    $entry['default_locale'] ?? ''
                ),
                'theme' => (string) ($entry['theme'] ?? ''),
                'status' => (string) ($entry['status'] ?? ''),
                'current' => $isCurrent,
                'singleton_compliant' => $singletonCompliant,
                'singleton_noncompliant' => !$singletonCompliant,
                'singleton_contract' => (string) (
                    $singleton['contract'] ?? ''
                ),
                'singleton_class' => (string) (
                    $singleton['class'] ?? ''
                ),
                'singleton_entrypoint' => (string) (
                    $singleton['entrypoint'] ?? ''
                ),
                'singleton_error' => (string) (
                    $singleton['error'] ?? ''
                ),
                'deletable' =>
                    ($entry['generated_by'] ?? null) === 'composer'
                    && ($entry['role'] ?? null)
                        === 'generated-opus-application'
                    && !in_array(
                        $entryId,
                        ['owasys-front', 'owasys-back'],
                        true
                    ),
            ];
        }

        $events = [];
        foreach ((array) ($result['recent_events'] ?? []) as $event) {
            if (!is_array($event)) {
                continue;
            }
            $events[] = [
                'type' => (string) (
                    $event['event_type'] ?? ''
                ),
                'application' => (string) (
                    $event['application_id'] ?? ''
                ),
                'created_at' => (string) (
                    $event['created_at'] ?? ''
                ),
            ];
        }

        $sync = is_array($result['sync'] ?? null)
            ? $result['sync']
            : [];
        $singletonCompliant = count(array_filter(
            $entries,
            static fn (array $entry): bool =>
                ($entry['singleton_compliant'] ?? false) === true
        ));
        $singletonNoncompliant = count($entries) - $singletonCompliant;
        $discoveryConflicts = [];

        foreach (
            (array) ($sync['discovery_conflicts'] ?? [])
            as $conflict
        ) {
            if (!is_array($conflict)) {
                continue;
            }

            $rejected = array_values(array_filter(
                (array) ($conflict['rejected_roots'] ?? []),
                'is_string'
            ));
            $discoveryConflicts[] = [
                'id' => (string) ($conflict['id'] ?? ''),
                'canonical_root' => (string) (
                    $conflict['canonical_root'] ?? ''
                ),
                'rejected_roots' => implode(', ', $rejected),
                'resolved' =>
                    ($conflict['resolved'] ?? false) === true,
                'unresolved' =>
                    ($conflict['resolved'] ?? false) !== true,
                'error' => (string) ($conflict['error'] ?? ''),
            ];
        }

        $canSelect = $this->security->isAllowed(
            $identity,
            'registry',
            'select'
        );
        $canCreate = $this->security->isAllowed(
            $identity,
            'creation',
            'open'
        );
        $canDelete = $this->security->isAllowed(
            $identity,
            'registry',
            'delete'
        );

        return [
            'registry' => [
                'can_select' => $canSelect,
                'cannot_select' => !$canSelect,
                'can_clear' => $canSelect,
                'can_create' => $canCreate,
                'can_delete' => $canDelete,
                'empty' => $entries === [],
                'events_empty' => $events === [],
                'error_application_required' =>
                    ($result['error'] ?? null)
                        === 'registry.error.application_required',
                'error_application_not_found' =>
                    ($result['error'] ?? null)
                        === 'registry.error.application_not_found',
                'error_action_invalid' =>
                    ($result['error'] ?? null)
                        === 'registry.error.action_invalid',
                'error_application_protected' =>
                    ($result['error'] ?? null)
                        === 'registry.error.application_protected',
                'error_delete_confirmation' =>
                    ($result['error'] ?? null)
                        === 'registry.error.delete_confirmation',
                'singleton_all_compliant' =>
                    $entries !== [] && $singletonNoncompliant === 0,
                'singleton_has_noncompliant' =>
                    $singletonNoncompliant > 0,
                'discovery_clean' => $discoveryConflicts === [],
                'discovery_has_conflicts' =>
                    $discoveryConflicts !== [],
            ],
            'entries' => $entries,
            'events' => $events,
            'discovery_conflicts' => $discoveryConflicts,
            'sync' => [
                'database' => (string) ($sync['database'] ?? ''),
                'total' => (string) ($sync['total'] ?? 0),
                'seed_imported' => (string) (
                    $sync['seed_imported'] ?? 0
                ),
                'discovered_imported' => (string) (
                    $sync['discovered_imported'] ?? 0
                ),
                'discovered_candidates' => (string) (
                    $sync['discovered_candidates'] ?? 0
                ),
                'duplicate_ids' => (string) (
                    $sync['duplicate_ids'] ?? 0
                ),
                'duplicate_roots' => (string) (
                    $sync['duplicate_roots'] ?? 0
                ),
                'singleton_compliant' =>
                    (string) $singletonCompliant,
                'singleton_noncompliant' =>
                    (string) $singletonNoncompliant,
            ],
        ];
    }

    /** @param array<string,mixed> $transition */
    private function actionHandlersFor(
        array $transition
    ): OwasysFsmActionHandlers {
        $actions = is_array($transition['actions'] ?? null)
            ? $transition['actions']
            : [];
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
                $this->profiler
            );
        }

        return $this->registryModel;
    }

    private function registryController(): OwasysRegistryController
    {
        if (
            !$this->registryController
                instanceof OwasysRegistryController
        ) {
            $this->registryController = new OwasysRegistryController(
                $this->registryModel()
            );
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
        $relative = trim(
            str_replace(
                '\\',
                '/',
                (string) ($navigation['fsm'] ?? '')
            ),
            '/'
        );
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException(
                'OWASYS_FSM_CONFIG_PATH_INVALID'
            );
        }

        return $this->readJson(
            $this->siteRoot . '/' . $relative,
            'OWASYS_FSM_CONFIG_INVALID'
        );
    }

    /** @return array<string,mixed> */
    private function readJson(string $path, string $error): array
    {
        try {
            return StructuredFileLoader::instance()->read($path);
        } catch (Throwable $cause) {
            throw new RuntimeException(
                $error
                    . ':'
                    . $path
                    . ':'
                    . $cause->getMessage(),
                0,
                $cause
            );
        }
    }

    private function routeUrl(
        string $locale,
        string $route
    ): string {
        return $this->localizedRoutes->url(
            $this->basePath(),
            $locale,
            $route
        );
    }

    private function basePath(): string
    {
        $script = str_replace(
            '\\',
            '/',
            (string) ($_SERVER['SCRIPT_NAME'] ?? '')
        );
        $directory = str_replace('\\', '/', dirname($script));

        return in_array($directory, ['/', '.', ''], true)
            ? ''
            : rtrim($directory, '/');
    }

    /** @param array<string,string> $query */
    private function redirect(
        string $locale,
        string $route,
        array $query = []
    ): never {
        $url = $this->routeUrl($locale, $route);
        if ($query !== []) {
            $url .= '?'
                . http_build_query(
                    $query,
                    '',
                    '&',
                    PHP_QUERY_RFC3986
                );
        }
        header('Location: ' . $url, true, 303);
        exit;
    }

    private function redirectExternal(string $url): never
    {
        if (
            $url === ''
            || str_contains($url, "\r")
            || str_contains($url, "\n")
            || str_contains($url, "\0")
        ) {
            throw new RuntimeException(
                'OWASYS_EXTERNAL_REDIRECT_INVALID'
            );
        }

        $parts = parse_url($url);
        $scheme = is_array($parts)
            ? strtolower(trim((string) ($parts['scheme'] ?? '')))
            : '';
        $host = is_array($parts)
            ? strtolower(trim((string) ($parts['host'] ?? '')))
            : '';
        $port = is_array($parts) && isset($parts['port'])
            ? (int) $parts['port']
            : 0;

        if (
            !is_array($parts)
            || $scheme !== 'http'
            || !in_array(
                $host,
                ['127.0.0.1', 'localhost', '::1'],
                true
            )
            || $port < 1024
            || $port > 65535
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new RuntimeException(
                'OWASYS_EXTERNAL_REDIRECT_INVALID'
            );
        }

        header('Location: ' . $url, true, 303);
        exit;
    }

    private function fail(int $status, string $message): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        exit($message);
    }
}
