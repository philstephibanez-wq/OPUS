<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\File\StructuredFileLoader;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;

/** OWASYS application creation workflow. All writes cross REST then Composer. */
final class OwasysCreationController
{
    private const FSM_SESSION_KEY = 'opus.fsm.owasys-front';
    private const WIZARD_FSM_SESSION_KEY =
        'opus.fsm.owasys-front.creation-wizard';
    private const DRAFT_SESSION_KEY = 'owasys.creation.blueprint';

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
        private readonly OwasysSessionRuntimeInterface $sessionRuntime,
        private readonly OwasysApplicationCreationModel $creation
    ) {
        $this->locales = new OwasysLocaleRegistry($siteConfig);
        $this->navigation = new OwasysNavigationBuilder($security);
        $this->logger = new Logger($siteRoot . '/var/logs', 'owasys-front.log');
        $this->profiler = new Profiler($siteRoot . '/var/profiler');
    }

    public function matchesCurrentRequest(): bool
    {
        [, $route] = $this->resolveRequest();
        return $route === 'applications/new';
    }

    public function run(): void
    {
        $this->sessionRuntime->start();
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
        $fsmStore = new FsmSessionStore(self::FSM_SESSION_KEY);
        $fsmStore->restore($fsm);
        $state = $this->enterCreationState($fsm, $fsmStore, $identity);
        $wizard = FsmProcessor::fromJsonFile(
            $this->siteRoot . '/config/creation.wizard.fsm.json'
        );
        $wizardStore = new FsmSessionStore(
            self::WIZARD_FSM_SESSION_KEY
        );
        $wizardStore->restore($wizard);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'GET') {
            if ((string) ($_GET['owasys_new'] ?? '') === '1') {
                unset($_SESSION[self::DRAFT_SESSION_KEY]);
                $wizard->reset();
                $wizardStore->persist($wizard);
            }
            $draft = $this->draft();
            $draft['step'] = $wizard->currentState();
            $this->render(
                $fsmConfig,
                $state,
                $locale,
                $identity,
                $draft,
                null
            );
            return;
        }
        if ($method !== 'POST') {
            $this->render($fsmConfig, $state, $locale, $identity, [], 'creation.error.method');
            return;
        }

        $action = trim((string) ($_POST['owasys_action'] ?? ''));
        if ($action === 'cancel-creation') {
            unset($_SESSION[self::DRAFT_SESSION_KEY]);
            $wizard->reset();
            $wizardStore->persist($wizard);
            $transition = $fsm->transition($state, 'cancel_creation', [
                'identity' => $identity,
                'is_authenticated' => true,
            ]);
            $fsmStore->persist($fsm);
            $this->redirect($locale, 'applications');
        }
        if ($action === 'previous-basics') {
            $draft = $this->draft();
            $transition = $wizard->transition(
                $wizard->currentState(),
                'return_basics'
            );
            $draft['step'] = (string) $transition['to_state'];
            $wizardStore->persist($wizard);
            $this->storeDraft($draft);
            $this->render($fsmConfig, $state, $locale, $identity, $draft, null);
            return;
        }
        if ($action === 'previous-security') {
            $draft = $this->draft();
            $transition = $wizard->transition(
                $wizard->currentState(),
                'return_security'
            );
            $draft['step'] = (string) $transition['to_state'];
            $wizardStore->persist($wizard);
            $this->storeDraft($draft);
            $this->render($fsmConfig, $state, $locale, $identity, $draft, null);
            return;
        }
        if ($action === 'next-security') {
            try {
                $draft = $this->basicsDraft();
                $transition = $wizard->transition(
                    $wizard->currentState(),
                    'continue_security'
                );
                $draft['step'] = (string) $transition['to_state'];
                $wizardStore->persist($wizard);
                $this->storeDraft($draft);
                $this->render(
                    $fsmConfig,
                    $state,
                    $locale,
                    $identity,
                    $draft,
                    null
                );
            } catch (Throwable $error) {
                http_response_code(422);
                $diagnostic = $this->recordValidationFailure(
                    'basics',
                    $error
                );
                $this->render(
                    $fsmConfig,
                    $state,
                    $locale,
                    $identity,
                    array_replace($this->draft(), [
                        'site_id' => strtolower(trim((string) (
                            $_POST['owasys_site_id'] ?? ''
                        ))),
                        'profile' => strtolower(trim((string) (
                            $_POST['owasys_profile'] ?? ''
                        ))),
                        'step' => 'basics',
                        ...$diagnostic,
                    ]),
                    $this->creationErrorKey($diagnostic['error_code'])
                );
            }
            return;
        }
        if ($action === 'review-creation') {
            try {
                $draft = $this->securityDraft($this->draft());
                $transition = $wizard->transition(
                    $wizard->currentState(),
                    'continue_review'
                );
                $draft['step'] = (string) $transition['to_state'];
                $wizardStore->persist($wizard);
                $this->storeDraft($draft);
                $this->render(
                    $fsmConfig,
                    $state,
                    $locale,
                    $identity,
                    $draft,
                    null
                );
            } catch (Throwable $error) {
                http_response_code(422);
                $draft = $this->draft();
                $draft['step'] = 'security';
                $draft = $this->securityInputDraft($draft);
                $diagnostic = $this->recordValidationFailure(
                    'security',
                    $error
                );
                $this->render(
                    $fsmConfig,
                    $state,
                    $locale,
                    $identity,
                    array_replace($draft, $diagnostic),
                    $this->creationErrorKey($diagnostic['error_code'])
                );
            }
            return;
        }
        if ($action !== 'confirm-creation') {
            $this->render($fsmConfig, $state, $locale, $identity, [], 'creation.error.action');
            return;
        }

        $this->security->assertAllowed($identity, 'creation', 'write');
        $draft = $this->draft();
        if (($draft['step'] ?? null) !== 'review') {
            throw new RuntimeException(
                'OWASYS_CREATION_REVIEW_REQUIRED'
            );
        }
        if ($wizard->currentState() !== 'review') {
            throw new RuntimeException(
                'OWASYS_CREATION_WIZARD_STATE_INVALID'
            );
        }
        $siteId = (string) ($draft['site_id'] ?? '');
        $profile = (string) ($draft['profile'] ?? '');
        $blueprint = $this->blueprint($draft);
        $parentTraceId = trim((string) getenv('OPUS_TRACE_ID'));
        $trace = $this->profiler->start(
            preg_match('/^[a-f0-9]{16,64}$/D', $parentTraceId) === 1
                ? $parentTraceId
                : null
        );
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
            $result = $this->creation->create(
                $siteId,
                $profile,
                $blueprint,
                $identity
            );
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
            $fsmStore->persist($fsm);
            unset($_SESSION[self::DRAFT_SESSION_KEY]);
            $wizard->reset();
            $wizardStore->persist($wizard);

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
                $fsmStore->persist($fsm);
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
                    ...$draft,
                    'step' => 'review',
                    'trace_id' => $traceId,
                    'error_code' => $code,
                ],
                $this->creationErrorKey($code)
            );
        }
    }

    /** @param array<string,mixed> $identity */
    private function enterCreationState(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        array $identity
    ): string
    {
        $current = $fsm->currentState();
        if ($current !== 'creation') {
            $transition = $fsm->transition($current, 'open_creation', [
                'identity' => $identity,
                'is_authenticated' => true,
                'roles' => is_array($identity['roles'] ?? null) ? $identity['roles'] : [],
            ]);
            $current = (string) $transition['to_state'];
            $store->persist($fsm);
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
        $profile = (string) ($form['profile'] ?? '');
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
                'step_basics' => ($form['step'] ?? 'basics') === 'basics',
                'step_security' => ($form['step'] ?? '') === 'security',
                'step_review' => ($form['step'] ?? '') === 'review',
                'site_id' => (string) ($form['site_id'] ?? ''),
                'profile' => $profile,
                'profile_frontend' => $profile === 'frontend',
                'profile_backend' => $profile === 'backend',
                'profile_fullstack' => $profile === 'fullstack',
                'authentication_required' =>
                    ($form['authentication_required'] ?? false) === true,
                'login_page' => ($form['login_page'] ?? false) === true,
                'provider_session' =>
                    ($form['provider'] ?? 'session') === 'session',
                'provider_local_password' =>
                    ($form['provider'] ?? '') === 'local-password',
                'provider_auth0_proxy' =>
                    ($form['provider'] ?? '') === 'auth0-proxy',
                'provider' => (string) ($form['provider'] ?? 'session'),
                'roles' => implode(', ', is_array($form['roles'] ?? null)
                    ? $form['roles'] : ['anonymous', 'admin']),
                'permissions' => implode(', ', is_array(
                    $form['permissions'] ?? null
                ) ? $form['permissions'] : ['home:view']),
                'home_roles' => implode(', ', is_array(
                    $form['home_roles'] ?? null
                ) ? $form['home_roles'] : ['anonymous', 'admin']),
                'initial_users' => implode(', ', is_array(
                    $form['initial_users'] ?? null
                ) ? $form['initial_users'] : []),
                'initial_user_role' =>
                    (string) ($form['initial_user_role'] ?? 'admin'),
                'locales_summary' => 'bg, hr, cs, da, nl, en, et, fi, fr, de, el, hu, ga, it, lv, lt, mt, pl, pt, ro, sk, sl, es, sv, uk',
                'has_error' => $errorKey !== null,
                'error_site_id' => $errorKey === 'creation.error.site_id',
                'error_profile' => $errorKey === 'creation.error.profile',
                'error_exists' => $errorKey === 'creation.error.exists',
                'error_backend' => $errorKey === 'creation.error.backend',
                'error_security_provider' =>
                    $errorKey === 'creation.error.security_provider',
                'error_security_login' =>
                    $errorKey === 'creation.error.security_login',
                'error_security_roles' =>
                    $errorKey === 'creation.error.security_roles',
                'error_security_home_roles' =>
                    $errorKey === 'creation.error.security_home_roles',
                'error_security_permissions' =>
                    $errorKey === 'creation.error.security_permissions',
                'error_security_users' =>
                    $errorKey === 'creation.error.security_users',
                'error_security_user_role' =>
                    $errorKey === 'creation.error.security_user_role',
                'error_action' => $errorKey === 'creation.error.action',
                'error_method' => $errorKey === 'creation.error.method',
                'trace_id' => (string) ($form['trace_id'] ?? ''),
                'error_code' => (string) ($form['error_code'] ?? ''),
            ],
        ];
        header('Content-Type: text/html; charset=UTF-8');
        $this->renderer->emit('creation/templates/index.score', $data);
    }

    /** @return array<string,mixed> */
    private function draft(): array
    {
        $draft = $_SESSION[self::DRAFT_SESSION_KEY] ?? null;
        return is_array($draft) ? $draft : [
            'step' => 'basics',
            'site_id' => '',
            'profile' => '',
            'authentication_required' => false,
            'login_page' => false,
            'provider' => 'session',
                'roles' => ['anonymous', 'admin'],
                'permissions' => ['home:view'],
                'home_roles' => ['anonymous', 'admin'],
            'initial_users' => [],
            'initial_user_role' => 'admin',
        ];
    }

    /** @param array<string,mixed> $draft */
    private function storeDraft(array $draft): void
    {
        $_SESSION[self::DRAFT_SESSION_KEY] = $draft;
    }

    /** @return array<string,mixed> */
    private function basicsDraft(): array
    {
        $siteId = strtolower(trim((string) (
            $_POST['owasys_site_id'] ?? ''
        )));
        $profile = strtolower(trim((string) (
            $_POST['owasys_profile'] ?? ''
        )));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1) {
            throw new RuntimeException('OWASYS_CREATION_SITE_ID_INVALID');
        }
        if (!in_array(
            $profile,
            ['frontend', 'backend', 'fullstack'],
            true
        )) {
            throw new RuntimeException('OWASYS_CREATION_PROFILE_INVALID');
        }
        return array_replace($this->draft(), [
            'site_id' => $siteId,
            'profile' => $profile,
        ]);
    }

    /** @param array<string,mixed> $draft @return array<string,mixed> */
    private function securityDraft(array $draft): array
    {
        $authentication = isset($_POST['owasys_authentication_required']);
        $login = isset($_POST['owasys_login_page']);
        $provider = strtolower(trim((string) (
            $_POST['owasys_provider'] ?? 'session'
        )));
        if (!in_array(
            $provider,
            ['session', 'local-password', 'auth0-proxy'],
            true
        )) {
            throw new RuntimeException(
                'OWASYS_CREATION_PROVIDER_INVALID'
            );
        }
        if ($login && !$authentication) {
            throw new RuntimeException(
                'OWASYS_CREATION_LOGIN_WITHOUT_AUTH'
            );
        }
        if ($provider === 'local-password' && !$login) {
            throw new RuntimeException(
                'OWASYS_CREATION_LOCAL_LOGIN_REQUIRED'
            );
        }
        if ($login && $provider !== 'local-password') {
            throw new RuntimeException(
                'OWASYS_CREATION_LOGIN_PROVIDER_INVALID'
            );
        }
        $roles = $this->identifierList(
            (string) ($_POST['owasys_roles'] ?? ''),
            false
        );
        $homeRoles = $this->identifierList(
            (string) ($_POST['owasys_home_roles'] ?? ''),
            false
        );
        if (array_diff($homeRoles, $roles) !== []) {
            throw new RuntimeException(
                'OWASYS_CREATION_HOME_ROLE_UNKNOWN'
            );
        }
        $permissions = $this->permissionList(
            (string) ($_POST['owasys_permissions'] ?? '')
        );
        if (!$authentication && ($login || $provider !== 'session')) {
            throw new RuntimeException(
                'OWASYS_CREATION_PUBLIC_PROVIDER_INVALID'
            );
        }
        if ($authentication && in_array('anonymous', $homeRoles, true)) {
            throw new RuntimeException(
                'OWASYS_CREATION_AUTH_HOME_ANONYMOUS'
            );
        }
        $users = $this->identifierList(
            (string) ($_POST['owasys_initial_users'] ?? ''),
            true
        );
        if ($users !== [] && $provider !== 'local-password') {
            throw new RuntimeException(
                'OWASYS_CREATION_USERS_PROVIDER_INVALID'
            );
        }
        $initialUserRole = strtolower(trim((string) (
            $_POST['owasys_initial_user_role'] ?? ''
        )));
        if ($users !== []
            && !in_array($initialUserRole, $roles, true)) {
            throw new RuntimeException(
                'OWASYS_CREATION_USER_ROLE_UNKNOWN'
            );
        }
        return array_replace($draft, [
            'authentication_required' => $authentication,
            'login_page' => $login,
            'provider' => $provider,
            'roles' => $roles,
            'permissions' => $permissions,
            'home_roles' => $homeRoles,
            'initial_users' => $users,
            'initial_user_role' => $users === [] ? '' : $initialUserRole,
        ]);
    }

    /** @param array<string,mixed> $draft @return array<string,mixed> */
    private function securityInputDraft(array $draft): array
    {
        return array_replace($draft, [
            'authentication_required' =>
                isset($_POST['owasys_authentication_required']),
            'login_page' => isset($_POST['owasys_login_page']),
            'provider' => strtolower(trim((string) (
                $_POST['owasys_provider'] ?? 'session'
            ))),
            'roles' => $this->submittedList('owasys_roles'),
            'permissions' => $this->submittedList(
                'owasys_permissions'
            ),
            'home_roles' => $this->submittedList(
                'owasys_home_roles'
            ),
            'initial_users' => $this->submittedList(
                'owasys_initial_users'
            ),
            'initial_user_role' => strtolower(trim((string) (
                $_POST['owasys_initial_user_role'] ?? ''
            ))),
        ]);
    }

    /** @return list<string> */
    private function submittedList(string $name): array
    {
        $result = [];
        foreach (preg_split(
            '/[\s,;]+/',
            strtolower(trim((string) ($_POST[$name] ?? '')))
        ) ?: [] as $value) {
            if ($value !== '') {
                $result[] = $value;
            }
        }
        return $result;
    }

    /** @return array{trace_id:string,error_code:string} */
    private function recordValidationFailure(
        string $stage,
        Throwable $error
    ): array {
        $code = $this->safeErrorCode($error);
        $parentTraceId = trim((string) getenv('OPUS_TRACE_ID'));
        $trace = $this->profiler->start(
            preg_match('/^[a-f0-9]{16,64}$/D', $parentTraceId) === 1
                ? $parentTraceId
                : null
        );
        $traceId = $trace->getTraceId();
        $context = [
            'stage' => $stage,
            'error_code' => $code,
        ];
        $this->profiler->event(
            'owasys.creation',
            'creation.validation_failed',
            $context
        );
        $this->logger->warning(
            'owasys.creation',
            'creation.validation_failed',
            $context,
            $traceId
        );
        $this->profiler->stop([
            'status' => 'validation_failed',
            'workflow' => 'application_creation',
            ...$context,
        ]);
        return [
            'trace_id' => $traceId,
            'error_code' => $code,
        ];
    }

    /** @return list<string> */
    private function identifierList(string $value, bool $allowEmpty): array
    {
        $result = [];
        foreach (preg_split('/[\s,;]+/', strtolower(trim($value))) ?: [] as $id) {
            if ($id === '') {
                continue;
            }
            if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $id) !== 1) {
                throw new RuntimeException(
                    'OWASYS_CREATION_IDENTIFIER_INVALID'
                );
            }
            $result[$id] = true;
        }
        if ($result === [] && !$allowEmpty) {
            throw new RuntimeException(
                'OWASYS_CREATION_IDENTIFIER_REQUIRED'
            );
        }
        return array_keys($result);
    }

    /** @return list<string> */
    private function permissionList(string $value): array
    {
        $result = [];
        foreach (preg_split('/[\s,;]+/', strtolower(trim($value))) ?: [] as $id) {
            if ($id === '') {
                continue;
            }
            if (preg_match(
                '/^[a-z][a-z0-9.-]{0,63}:[a-z][a-z0-9.-]{0,63}$/D',
                $id
            ) !== 1) {
                throw new RuntimeException(
                    'OWASYS_CREATION_PERMISSION_INVALID'
                );
            }
            $result[$id] = true;
        }
        if ($result === []) {
            throw new RuntimeException(
                'OWASYS_CREATION_PERMISSION_REQUIRED'
            );
        }
        return array_keys($result);
    }

    /** @param array<string,mixed> $draft @return array<string,mixed> */
    private function blueprint(array $draft): array
    {
        return [
            'contract' => 'OPUS_SITE_CREATION_BLUEPRINT_V1',
            'security' => [
                'authentication_required' =>
                    ($draft['authentication_required'] ?? false) === true,
                'login_page' => ($draft['login_page'] ?? false) === true,
                'provider' => (string) ($draft['provider'] ?? ''),
                'roles' => $draft['roles'] ?? [],
                'permissions' => $draft['permissions'] ?? [],
                'home_roles' => $draft['home_roles'] ?? [],
                'initial_users' => $draft['initial_users'] ?? [],
                'initial_user_role' =>
                    (string) ($draft['initial_user_role'] ?? ''),
            ],
        ];
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

    private function creationErrorKey(string $code): string
    {
        return match (true) {
            str_contains($code, 'SITE_ID') || str_contains($code, 'APPLICATION_ID') => 'creation.error.site_id',
            str_contains($code, 'PROFILE') => 'creation.error.profile',
            str_contains($code, 'PATH_ALREADY_EXISTS') || str_contains($code, 'ALREADY_EXISTS') => 'creation.error.exists',
            str_contains($code, 'LOGIN_') ||
                str_contains($code, 'PUBLIC_PROVIDER') =>
                'creation.error.security_login',
            str_contains($code, 'PROVIDER_INVALID') =>
                'creation.error.security_provider',
            str_contains($code, 'HOME_ROLE') ||
                str_contains($code, 'AUTH_HOME_ANONYMOUS') =>
                'creation.error.security_home_roles',
            str_contains($code, 'PERMISSION') =>
                'creation.error.security_permissions',
            str_contains($code, 'USERS_PROVIDER') =>
                'creation.error.security_users',
            str_contains($code, 'USER_ROLE') =>
                'creation.error.security_user_role',
            str_contains($code, 'IDENTIFIER') =>
                'creation.error.security_roles',
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
