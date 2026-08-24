<?php
declare(strict_types=1);

use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Http\LocalizedRouteResolverInterface;
use Opus\Http\Response;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Csrf\CsrfTokenManager;

/** Security workspace for the currently selected OPUS application. */
final class OwasysSecurityController
{
    private const FSM_SESSION_KEY = 'opus.fsm.owasys-front';
    private const SECURITY_MUTATION_FSM_SESSION_KEY =
        'opus.fsm.owasys-front.security-mutation';
    private const VIEWS = [
        'overview',
        'identities',
        'roles',
        'permissions',
        'assignments',
        'resources',
        'sso',
    ];

    private readonly OwasysLocaleRegistry $locales;
    private readonly OwasysNavigationBuilder $navigation;
    private readonly CsrfTokenManager $csrf;

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
        $this->csrf = new CsrfTokenManager();
    }

    public function matchesCurrentRequest(): bool
    {
        [, $route] = $this->resolveRequest();
        return $route === 'security'
            || str_starts_with($route, 'security/');
    }

    public function run(): void
    {
        $this->sessionRuntime->start();
        [$locale, $route] = $this->resolveRequest();
        if ($route !== 'security'
            && !str_starts_with($route, 'security/')) {
            throw new RuntimeException('OWASYS_SECURITY_ROUTE_MISMATCH');
        }

        $identity = $this->session->user();
        if (!is_array($identity)) {
            $this->redirect($locale, 'login');
            return;
        }
        $currentApp = $this->session->currentApp();
        if (!is_array($currentApp)) {
            $this->redirect($locale, 'applications');
            return;
        }

        $this->security->assertAllowed($identity, 'security', 'open');
        $fsmConfig = $this->fsmConfig();
        $fsm = FsmSiteLoader::processorForSiteRoot(
            $this->siteRoot,
            (new OwasysFsmGuardHandlers($this->security))
                ->forConfig($fsmConfig, $identity),
            $this->profiler,
            $this->parentSpanId
        );
        $store = new FsmSessionStore(self::FSM_SESSION_KEY);
        $store->restore($fsm);
        $state = $this->enterSecurityState(
            $fsm,
            $store,
            $identity,
            $currentApp
        );

        $siteId = strtolower(trim((string) ($currentApp['id'] ?? '')));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1) {
            throw new RuntimeException('OWASYS_SECURITY_CURRENT_APP_INVALID');
        }
        $runtimeCoordinator = new OwasysSecurityRuntimeCoordinator(
            $this->siteRoot,
            $this->siteConfig,
            $this->profiler,
            $this->parentSpanId
        );
        $runtime = $runtimeCoordinator->enter($identity, $siteId);
        if ((string) ($runtime['navigation_state'] ?? '') !== $state
            || (string) ($runtime['security_state'] ?? '') !== 'authenticated') {
            throw new RuntimeException('OWASYS_SECURITY_RUNTIME_COORDINATION_INVALID');
        }
        $view = $this->securityView($locale, $route);
        if ($view === null) {
            return;
        }

        $mutationResult = null;
        $mutationError = null;
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $mutationFsm = $this->securityMutationFsm();
            $mutationStore = new FsmSessionStore(
                self::SECURITY_MUTATION_FSM_SESSION_KEY
            );
            try {
                $phase = strtolower(trim((string) (
                    $_POST['owasys_security_phase'] ?? ''
                )));
                if (!in_array($phase, ['preview', 'commit'], true)) {
                    throw new RuntimeException(
                        'OWASYS_SECURITY_MUTATION_PHASE_INVALID'
                    );
                }

                $mutation = $this->mutationFromPost($view, $_POST);
                $reason = trim((string) (
                    $_POST['owasys_security_reason'] ?? ''
                ));

                if ($phase === 'preview') {
                    $mutationStore->clear();
                    $mutationFsm->reset();
                    $mutationFsm->transition('idle', 'request');
                } else {
                    $mutationStore->restore($mutationFsm);
                    if ($mutationFsm->currentState() !== 'previewed') {
                        throw new RuntimeException(
                            'OWASYS_SECURITY_MUTATION_WORKFLOW_STATE_INVALID'
                        );
                    }
                    $this->assertSecurityMutationWorkflowBinding(
                        $mutationFsm,
                        $siteId,
                        $mutation,
                        $reason,
                        $view
                    );
                }

                $freshAuthProof = $runtimeCoordinator->reauthenticate(
                    $identity,
                    $siteId,
                    fn (): string => $this->security->reauthenticate(
                        $identity,
                        (string) ($_POST['owasys_reauth_password'] ?? ''),
                        $siteId,
                        $mutation,
                        $phase
                    )
                );
                unset($_POST['owasys_reauth_password']);

                if ($phase === 'preview') {
                    $mutationFsm->transition(
                        'requested',
                        'authenticate'
                    );
                }

                $this->security->assertAllowed(
                    $identity,
                    'security',
                    'manage'
                );
                if ($phase === 'preview') {
                    $mutationFsm->transition(
                        'authenticated',
                        'authorize'
                    );
                }

                $this->csrf->assertValid(
                    $this->csrfScope($siteId),
                    (string) ($_POST['owasys_csrf_token'] ?? '')
                );

                if ($phase === 'preview') {
                    $this->bindSecurityMutationWorkflow(
                        $mutationFsm,
                        $siteId,
                        $mutation,
                        $reason,
                        $view
                    );
                    $mutationFsm->transition('authorized', 'validate');

                    $mutationResult = $this->security
                        ->previewSecurityMutation(
                            $identity,
                            $siteId,
                            $mutation,
                            $reason,
                            $freshAuthProof
                        );
                    $mutationFsm->transition('validated', 'preview');
                    $mutationStore->persist($mutationFsm);
                } else {
                    $mutationFsm->transition('previewed', 'confirm');
                    try {
                        $mutationResult = $this->security
                            ->commitSecurityMutation(
                                $identity,
                                $siteId,
                                $mutation,
                                $reason,
                                $freshAuthProof,
                                (string) (
                                    $_POST['owasys_expected_state_hash'] ?? ''
                                ),
                                (string) (
                                    $_POST['owasys_confirmation_token'] ?? ''
                                )
                            );
                        $mutationFsm->transition('confirmed', 'commit');
                    } catch (RuntimeException $commitError) {
                        if (str_contains(
                            $commitError->getMessage(),
                            'ROLLED_BACK'
                        )) {
                            $mutationFsm->transition(
                                'confirmed',
                                'rollback'
                            );
                        } else {
                            $mutationFsm->transition(
                                $mutationFsm->currentState(),
                                'reject'
                            );
                        }
                        $mutationStore->clear();
                        throw $commitError;
                    }
                    $mutationStore->clear();
                }
            } catch (RuntimeException $error) {
                unset($_POST['owasys_reauth_password']);
                if (isset($mutationFsm, $mutationStore)
                    && $mutationFsm instanceof FsmProcessor
                    && !in_array(
                        $mutationFsm->currentState(),
                        ['rejected', 'rolled_back', 'committed'],
                        true
                    )) {
                    try {
                        $mutationFsm->transition(
                            $mutationFsm->currentState(),
                            'reject'
                        );
                    } catch (RuntimeException) {
                    }
                    $mutationStore->clear();
                }
                $mutationError = $this->safeMutationError($error);
            }        } elseif ($method !== 'GET') {
            throw new RuntimeException(
                'OWASYS_SECURITY_HTTP_METHOD_NOT_ALLOWED'
            );
        }

        $snapshot = $this->security->securitySnapshot($identity, $siteId);
        $this->render(
            $fsmConfig,
            $state,
            $locale,
            $identity,
            $currentApp,
            $snapshot,
            $view,
            $mutationResult,
            $mutationError
        );
    }

    private function securityMutationFsm(): FsmProcessor
    {
        return FsmProcessor::fromJsonFile(
            $this->siteRoot . '/config/security.mutation.fsm.json',
            [],
            $this->profiler,
            $this->parentSpanId
        );
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private function bindSecurityMutationWorkflow(
        FsmProcessor $fsm,
        string $siteId,
        array $mutation,
        string $reason,
        string $view
    ): void {
        $fsm->poke('site_id', $siteId);
        $fsm->poke(
            'mutation_hash',
            $this->securityMutationWorkflowHash($mutation, $reason)
        );
        $fsm->poke('view', $view);
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private function assertSecurityMutationWorkflowBinding(
        FsmProcessor $fsm,
        string $siteId,
        array $mutation,
        string $reason,
        string $view
    ): void {
        if (!hash_equals((string) $fsm->peek('site_id'), $siteId)
            || !hash_equals(
                (string) $fsm->peek('mutation_hash'),
                $this->securityMutationWorkflowHash($mutation, $reason)
            )
            || !hash_equals((string) $fsm->peek('view'), $view)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_WORKFLOW_BINDING_MISMATCH'
            );
        }
    }

    /**
     * @param array<string,mixed> $mutation
     */
    private function securityMutationWorkflowHash(
        array $mutation,
        string $reason
    ): string {
        return hash(
            'sha256',
            Json::instance()->encode($mutation, false)
                . "\n"
                . $reason
        );
    }
    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     */
    private function enterSecurityState(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        array $identity,
        array $currentApp
    ): string {
        $current = $fsm->currentState();
        if ($current !== 'security') {
            $context = [
                'identity' => $identity,
                'is_authenticated' => true,
                'roles' => is_array($identity['roles'] ?? null)
                    ? $identity['roles']
                    : [],
                'current_app' => $currentApp,
                'has_current_app' => true,
            ];
            $transition = $fsm->transition(
                $current,
                'open_security',
                $context
            );
            $current = (string) ($transition['next_state'] ?? '');
            if ($current !== 'security') {
                throw new RuntimeException(
                    'OWASYS_SECURITY_FSM_TARGET_INVALID'
                );
            }
            $store->persist($fsm);
        }
        return $current;
    }

    /**
     * @param array<string,mixed> $fsmConfig
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     * @param array<string,mixed> $snapshot
     * @param array<string,mixed>|null $mutationResult
     */
    private function render(
        array $fsmConfig,
        string $state,
        string $locale,
        array $identity,
        array $currentApp,
        array $snapshot,
        string $view,
        ?array $mutationResult,
        ?string $mutationError
    ): void {
        $basePath = $this->basePath();
        $routeUrl = fn (string $target): string => $this->routeUrl(
            $locale,
            $target
        );
        $overview = is_array($snapshot['overview'] ?? null)
            ? $snapshot['overview']
            : [];
        $application = is_array($snapshot['application'] ?? null)
            ? $snapshot['application']
            : [];
        $capabilities = is_array(
            $snapshot['mutation_capabilities'] ?? null
        ) ? $snapshot['mutation_capabilities'] : [];
        $canManage = $this->security->isAllowed(
            $identity,
            'security',
            'manage'
        );
        $targetMutable = ($capabilities['target_mutable'] ?? false) === true;
        $reauthSupported = (string) ($identity['provider'] ?? '')
            === 'local-password';
        $canMutate = $canManage && $targetMutable && $reauthSupported;
        $mutationView = $this->mutationView($mutationResult);
        $securityIdentities = $this->normalizeIdentities(
            $this->rows($snapshot, 'identities')
        );
        $securityUsers = array_values(array_filter(
            $securityIdentities,
            static fn (array $row): bool =>
                ($row['identity_type'] ?? 'unknown') === 'user'
        ));
        $securityAgents = array_values(array_filter(
            $securityIdentities,
            static fn (array $row): bool =>
                ($row['identity_type'] ?? 'unknown') === 'agent'
        ));
        $securityUnknown = array_values(array_filter(
            $securityIdentities,
            static fn (array $row): bool =>
                ($row['identity_type'] ?? 'unknown') === 'unknown'
        ));

        $data = [
            'page' => ['title' => '', 'summary' => ''],
            'fsm' => ['state' => $state, 'module' => 'security'],
            'identity' => [
                'authenticated' => true,
                'label' => (string) ($identity['label'] ?? ''),
                'primary_role' => (string) (
                    $identity['roles'][0]
                    ?? $identity['profile']
                    ?? ''
                ),
            ],
            'current_app' => [
                'present' => true,
                'id' => (string) ($currentApp['id'] ?? ''),
                'name' => (string) (
                    $currentApp['name']
                    ?? $currentApp['id']
                    ?? ''
                ),
                'kind' => (string) ($currentApp['kind'] ?? ''),
                'root' => (string) ($currentApp['root_path'] ?? ''),
            ],
            'locale' => [
                'code' => $locale,
                'name' => $this->locales->name($locale),
                'flag' => $basePath
                    . '/asset/flags/'
                    . rawurlencode($this->locales->flagCode($locale))
                    . '.svg',
            ],
            'locales' => array_map(
                fn (string $code): array => [
                    'code' => $code,
                    'name' => $this->locales->name($code),
                    'flag' => $basePath
                        . '/asset/flags/'
                        . rawurlencode($this->locales->flagCode($code))
                        . '.svg',
                    'url' => $this->securityUrl($code, $view),
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
                'current' => $this->securityUrl($locale, $view),
            ],
            'navigation' => $this->navigation->build(
                $fsmConfig,
                $identity,
                $state,
                $currentApp,
                $routeUrl
            ),
            'auth' => [
                'provider' => $this->security->defaultProvider(),
                'can_change_password' => false,
                'cannot_change_password' => true,
                'error_required_credentials' => false,
                'error_invalid_credentials' => false,
                'error_password_mismatch' => false,
                'error_password_too_short' => false,
                'error_current_password_invalid' => false,
                'error_password_unchanged' => false,
                'error_runtime_user_missing' => false,
            ],
            'security' => [
                'read_only' => !$canMutate,
                'can_mutate' => $canMutate,
                'cannot_mutate' => !$canMutate,
                'target_protected' => !$targetMutable,
                'reauth_unsupported' => $canManage
                    && $targetMutable
                    && !$reauthSupported,
                'identity_reference_supported' => $canMutate
                    && ($capabilities['identity_reference'] ?? false) === true,
                'identity_update_supported' => $canMutate
                    && ($capabilities['identity_update'] ?? false) === true,
                'identity_delete_supported' => $canMutate
                    && ($capabilities['identity_delete'] ?? false) === true,
                'role_create_supported' => $canMutate
                    && ($capabilities['role_create'] ?? false) === true,
                'permission_grant_supported' => $canMutate
                    && ($capabilities['permission_grant'] ?? false) === true,
                'assignment_grant_supported' => $canMutate
                    && ($capabilities['assignment_grant'] ?? false) === true,
                'assignment_revoke_supported' => $canMutate
                    && ($capabilities['assignment_revoke'] ?? false) === true,
                'assignment_grant_unsupported' => $canMutate
                    && ($capabilities['assignment_grant'] ?? false) !== true,
                'resource_allow_supported' => $canMutate
                    && ($capabilities['resource_allow'] ?? false) === true,
                'destructive_mutations_supported' => $canMutate
                    && ($capabilities['destructive_mutations'] ?? false) === true,
                'mutation_preview' => is_array($mutationResult)
                    && ($mutationResult['contract'] ?? null)
                        === 'OWASYS_SECURITY_MUTATION_PREVIEW_V1',
                'mutation_committed' => is_array($mutationResult)
                    && ($mutationResult['contract'] ?? null)
                        === 'OWASYS_SECURITY_MUTATION_COMMIT_V1',
                'mutation_error' => is_string($mutationError)
                    && $mutationError !== '',
                'mutation_error_code' => (string) ($mutationError ?? ''),
                'mutation_error_workflow_state_invalid' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_MUTATION_WORKFLOW_STATE_INVALID',
                'mutation_error_last_administrator' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_LAST_ADMINISTRATOR_DELETE_FORBIDDEN',
                'mutation_error_identity_already_referenced' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_IDENTITY_ALREADY_REFERENCED',
                'mutation_error_identity_not_found' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_IDENTITY_NOT_FOUND',
                'mutation_error_identity_update_unchanged' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_IDENTITY_UPDATE_UNCHANGED',
                'mutation_error_role_already_exists' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_ROLE_ALREADY_EXISTS',
                'mutation_error_assignment_already_exists' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_ASSIGNMENT_ALREADY_EXISTS',
                'mutation_error_assignment_not_found' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_ASSIGNMENT_NOT_FOUND',
                'mutation_error_assignment_identity_not_found' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_ASSIGNMENT_IDENTITY_NOT_FOUND',
                'mutation_error_last_administrator_assignment' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_LAST_ADMINISTRATOR_'
                            . 'ASSIGNMENT_REVOKE_FORBIDDEN',
                'mutation_error_permission_already_granted' =>
                    (string) ($mutationError ?? '')
                        === 'OWASYS_SECURITY_PERMISSION_ALREADY_GRANTED',
                'view_overview' => $view === 'overview',
                'view_identities' => $view === 'identities',
                'view_roles' => $view === 'roles',
                'view_permissions' => $view === 'permissions',
                'view_assignments' => $view === 'assignments',
                'view_resources' => $view === 'resources',
                'view_sso' => $view === 'sso',
                'providers_empty' => $this->rows($snapshot, 'providers') === [],
                'providers_count' => count($this->rows($snapshot, 'providers')),
                'identities_empty' => $this->rows(
                    $snapshot,
                    'identities'
                ) === [],
                'roles_empty' => $this->rows($snapshot, 'roles') === [],
                'permissions_empty' => $this->rows(
                    $snapshot,
                    'permissions'
                ) === [],
                'assignments_empty' => $this->rows(
                    $snapshot,
                    'assignments'
                ) === [],
                'resources_empty' => $this->rows(
                    $snapshot,
                    'resources'
                ) === [],
                'identities_count' => count($securityIdentities),
                'users_count' => count($securityUsers),
                'agents_count' => count($securityAgents),
                'unknown_identities_count' => count($securityUnknown),
                'users_empty' => $securityUsers === [],
                'agents_empty' => $securityAgents === [],
                'unknown_identities_present' => $securityUnknown !== [],
                'roles_count' => count($this->rows($snapshot, 'roles')),
                'permissions_count' => count(
                    $this->rows($snapshot, 'permissions')
                ),
                'assignments_count' => count(
                    $this->rows($snapshot, 'assignments')
                ),
                'resources_count' => count(
                    $this->rows($snapshot, 'resources')
                ),
                'application_id' => (string) ($application['id'] ?? ''),
                'application_name' => (string) (
                    $application['name']
                    ?? $application['id']
                    ?? ''
                ),
                'application_kind' => (string) (
                    $application['kind'] ?? ''
                ),
                'acl_contract' => (string) (
                    $overview['acl_contract'] ?? ''
                ),
                'sso_contract' => (string) (
                    $overview['sso_contract'] ?? ''
                ),
                'default_policy' => (string) (
                    $overview['default_policy'] ?? ''
                ),
                'default_provider' => (string) (
                    $overview['default_provider'] ?? ''
                ),
                'onboarding_present' =>
                    ($overview['onboarding_present'] ?? false) === true,
                'onboarding_absent' =>
                    ($overview['onboarding_present'] ?? false) !== true,
                'authentication_known' => array_key_exists(
                    'authentication_required',
                    $overview
                ) && is_bool($overview['authentication_required']),
                'authentication_required' =>
                    ($overview['authentication_required'] ?? false) === true,
                'authentication_public' =>
                    ($overview['authentication_required'] ?? null) === false,
            ],
            'security_urls' => [
                'overview' => $this->securityUrl(
                    $locale,
                    'overview'
                ),
                'identities' => $this->securityUrl(
                    $locale,
                    'identities'
                ),
                'roles' => $this->securityUrl($locale, 'roles'),
                'permissions' => $this->securityUrl(
                    $locale,
                    'permissions'
                ),
                'assignments' => $this->securityUrl(
                    $locale,
                    'assignments'
                ),
                'resources' => $this->securityUrl(
                    $locale,
                    'resources'
                ),
                'sso' => $this->securityUrl(
                    $locale,
                    'sso'
                ),
            ],
            'csrf' => [
                'token' => $this->csrf->issue($this->csrfScope(
                    (string) ($application['id'] ?? '')
                )),
            ],
            'mutation' => $mutationView,
            'mutation_diff' => $this->mutationRows(
                $mutationResult,
                'diff'
            ),
            'mutation_gained' => $this->mutationStrings(
                $mutationResult,
                'access_delta',
                'gained'
            ),
            'mutation_lost' => $this->mutationStrings(
                $mutationResult,
                'access_delta',
                'lost'
            ),
            'mutation_affected' => $this->mutationStrings(
                $mutationResult,
                'affected_subjects'
            ),
            'providers' => $this->normalizeProviders(
                $this->rows($snapshot, 'providers')
            ),
            'identities' => $securityIdentities,
            'users' => $securityUsers,
            'agents' => $securityAgents,
            'unknown_identities' => $securityUnknown,
            'roles' => $this->normalizeRoles(
                $this->rows($snapshot, 'roles')
            ),
            'permissions' => $this->normalizePermissions(
                $this->rows($snapshot, 'permissions')
            ),
            'assignments' => $this->normalizeAssignments(
                $this->rows($snapshot, 'assignments')
            ),
            'resources' => $this->normalizeResources(
                $this->rows($snapshot, 'resources')
            ),
        ];

        header('Content-Type: text/html; charset=UTF-8');
        $this->renderer->emit('security/templates/index.score', $data);
    }

    /** @return list<array<string,mixed>> */
    private function rows(array $snapshot, string $key): array
    {
        $rows = $snapshot[$key] ?? null;
        if (!is_array($rows)) {
            return [];
        }
        return array_values(array_filter($rows, 'is_array'));
    }

    /** @return list<array<string,mixed>> */
    private function normalizeProviders(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'id' => (string) ($row['id'] ?? ''),
                'enabled' => ($row['enabled'] ?? false) === true,
                'disabled' => ($row['enabled'] ?? false) !== true,
            ],
            $rows
        );
    }

    /** @return list<array<string,mixed>> */
    private function normalizeIdentities(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'provider' => (string) ($row['provider'] ?? ''),
                'subject' => (string) ($row['subject'] ?? ''),
                'identity_type' => in_array(
                    strtolower(trim((string) (
                        $row['identity_type'] ?? ''
                    ))),
                    ['user', 'agent'],
                    true
                )
                    ? strtolower(trim((string) (
                        $row['identity_type']
                    )))
                    : 'unknown',
                'label' => (string) ($row['label'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'roles' => implode(', ', array_values(array_filter(
                    is_array($row['roles'] ?? null)
                        ? $row['roles']
                        : [],
                    'is_string'
                ))),
                'source' => (string) ($row['source'] ?? ''),
                'must_change_password' =>
                    ($row['must_change_password'] ?? false) === true,
            ],
            $rows
        );
    }

    /** @return list<array<string,mixed>> */
    private function normalizeRoles(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'id' => (string) ($row['id'] ?? ''),
                'permissions' => implode(', ', array_values(array_filter(
                    is_array($row['permissions'] ?? null)
                        ? $row['permissions']
                        : [],
                    'is_string'
                ))),
                'permissions_count' => (string) (
                    $row['permissions_count'] ?? 0
                ),
                'assignment_known' =>
                    ($row['assignment_known'] ?? false) === true,
                'assignment_unknown' =>
                    ($row['assignment_known'] ?? false) !== true,
            ],
            $rows
        );
    }

    /** @return list<array<string,mixed>> */
    private function normalizePermissions(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'id' => (string) ($row['id'] ?? ''),
                'resource' => (string) ($row['resource'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
            ],
            $rows
        );
    }

    /** @return list<array<string,mixed>> */
    private function normalizeAssignments(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'subject' => (string) ($row['subject'] ?? ''),
                'role' => (string) ($row['role'] ?? ''),
                'scope_type' => (string) ($row['scope_type'] ?? ''),
                'scope_id' => (string) ($row['scope_id'] ?? ''),
                'source' => (string) ($row['source'] ?? ''),
                'revocable' => str_contains(
                    (string) ($row['source'] ?? ''),
                    'runtime.local-password'
                ),
            ],
            $rows
        );
    }

    /** @return list<array<string,mixed>> */
    private function normalizeResources(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'resource' => (string) ($row['resource'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
                'allowed_roles' => implode(', ', array_values(array_filter(
                    is_array($row['allowed_roles'] ?? null)
                        ? $row['allowed_roles']
                        : [],
                    'is_string'
                ))),
                'source' => (string) ($row['source'] ?? ''),
            ],
            $rows
        );
    }

    /** @param array<string,mixed> $post @return array<string,string> */
    private function mutationFromPost(string $view, array $post): array
    {
        $type = strtolower(trim((string) (
            $post['owasys_security_mutation'] ?? ''
        )));
        $allowed = match ($view) {
            'identities' => [
                'identity.reference',
                'identity.update',
                'identity.delete',
            ],
            'roles' => ['role.create'],
            'permissions' => ['permission.grant'],
            'assignments' => [
                'assignment.grant',
                'assignment.revoke',
            ],
            'resources' => ['resource.allow'],
            default => [],
        };
        if (!in_array($type, $allowed, true)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_VIEW_MISMATCH'
            );
        }
        return match ($type) {
            'identity.reference' => [
                'type' => $type,
                'provider' => trim((string) (
                    $post['owasys_security_provider'] ?? ''
                )),
                'subject' => trim((string) (
                    $post['owasys_security_subject'] ?? ''
                )),
                'identity_type' => strtolower(trim((string) (
                    $post['owasys_security_identity_type'] ?? ''
                ))),
            ],
            'identity.update' => [
                'type' => $type,
                'provider' => trim((string) (
                    $post['owasys_security_provider'] ?? ''
                )),
                'subject' => trim((string) (
                    $post['owasys_security_subject'] ?? ''
                )),
                'identity_type' => strtolower(trim((string) (
                    $post['owasys_security_identity_type'] ?? ''
                ))),
            ],
            'identity.delete' => [
                'type' => $type,
                'provider' => trim((string) (
                    $post['owasys_security_provider'] ?? ''
                )),
                'subject' => trim((string) (
                    $post['owasys_security_subject'] ?? ''
                )),
            ],
            'role.create' => [
                'type' => $type,
                'role' => trim((string) (
                    $post['owasys_security_role'] ?? ''
                )),
            ],
            'permission.grant' => [
                'type' => $type,
                'role' => trim((string) (
                    $post['owasys_security_role'] ?? ''
                )),
                'permission' => trim((string) (
                    $post['owasys_security_permission'] ?? ''
                )),
            ],
            'assignment.grant', 'assignment.revoke' => [
                'type' => $type,
                'subject' => trim((string) (
                    $post['owasys_security_subject'] ?? ''
                )),
                'role' => trim((string) (
                    $post['owasys_security_role'] ?? ''
                )),
            ],
            'resource.allow' => [
                'type' => $type,
                'resource' => trim((string) (
                    $post['owasys_security_resource'] ?? ''
                )),
                'action' => trim((string) (
                    $post['owasys_security_action'] ?? ''
                )),
                'role' => trim((string) (
                    $post['owasys_security_role'] ?? ''
                )),
            ],
            default => throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_TYPE_INVALID'
            ),
        };
    }

    /**
     * @param array<string,mixed>|null $result
     * @return array<string,mixed>
     */
    private function mutationView(?array $result): array
    {
        $mutation = is_array($result['mutation'] ?? null)
            ? $result['mutation']
            : [];
        return [
            'type' => (string) ($mutation['type'] ?? ''),
            'provider' => (string) ($mutation['provider'] ?? ''),
            'subject' => (string) ($mutation['subject'] ?? ''),
            'identity_type' => (string) (
                $mutation['identity_type'] ?? ''
            ),
            'role' => (string) ($mutation['role'] ?? ''),
            'permission' => (string) ($mutation['permission'] ?? ''),
            'resource' => (string) ($mutation['resource'] ?? ''),
            'action' => (string) ($mutation['action'] ?? ''),
            'reason' => (string) ($result['reason'] ?? ''),
            'expected_state_hash' => (string) (
                $result['current_state_hash']
                ?? $result['before_state_hash']
                ?? ''
            ),
            'proposed_state_hash' => (string) (
                $result['proposed_state_hash']
                ?? $result['after_state_hash']
                ?? ''
            ),
            'confirmation_token' => (string) (
                $result['confirmation_token'] ?? ''
            ),
            'files' => implode(', ', array_values(array_filter(
                is_array($result['files'] ?? null)
                    ? $result['files']
                    : (is_array($result['files_written'] ?? null)
                        ? $result['files_written']
                        : []),
                'is_string'
            ))),
        ];
    }

    /**
     * @param array<string,mixed>|null $result
     * @return list<array<string,string>>
     */
    private function mutationRows(?array $result, string $key): array
    {
        if (!is_array($result)) {
            return [];
        }
        $rows = is_array($result[$key] ?? null) ? $result[$key] : [];
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = [
                'path' => (string) ($row['path'] ?? ''),
                'summary' => (string) ($row['summary'] ?? ''),
            ];
        }
        return $normalized;
    }

    /**
     * @param array<string,mixed>|null $result
     * @return list<array{value:string}>
     */
    private function mutationStrings(
        ?array $result,
        string $key,
        ?string $nested = null
    ): array {
        if (!is_array($result)) {
            return [];
        }
        $values = $result[$key] ?? [];
        if ($nested !== null) {
            $values = is_array($values) ? ($values[$nested] ?? []) : [];
        }
        if (!is_array($values)) {
            return [];
        }
        return array_map(
            static fn (string $value): array => ['value' => $value],
            array_values(array_filter($values, 'is_string'))
        );
    }

    private function csrfScope(string $siteId): string
    {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1) {
            throw new RuntimeException('OWASYS_SECURITY_CURRENT_APP_INVALID');
        }
        return 'owasys.security.mutation.' . $siteId;
    }

    private function safeMutationError(RuntimeException $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/D', $message) === 1
            ? $message
            : 'OWASYS_SECURITY_MUTATION_FAILED';
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

        $default = (string) (
            $this->siteConfig['default_locale'] ?? 'fr-FR'
        );
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
        $publicRoute = implode('/', $segments);
        return [
            $locale,
            $publicRoute === ''
                ? ''
                : $this->localizedRoutes->resolve(
                    $locale,
                    $publicRoute
                ),
        ];
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
            throw new RuntimeException(
                'OWASYS_SECURITY_FSM_PATH_INVALID'
            );
        }
        return StructuredFileLoader::instance()->read(
            $this->siteRoot . '/' . $relative
        );
    }

    private function securityView(string $locale, string $route): ?string
    {
        if ($route === 'security') {
            $legacyView = strtolower(trim((string) (
                $_GET['view'] ?? ''
            )));

            if ($legacyView === '') {
                return 'overview';
            }

            if (!in_array($legacyView, self::VIEWS, true)) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_VIEW_INVALID'
                );
            }

            if (strtoupper((string) (
                $_SERVER['REQUEST_METHOD'] ?? 'GET'
            )) === 'GET') {
                Response::empty(303, [
                    'Location' => $this->securityUrl($locale, $legacyView),
                ])->send();
                return null;
            }

            return $legacyView;
        }

        if (!str_starts_with($route, 'security/')) {
            throw new RuntimeException(
                'OWASYS_SECURITY_ROUTE_MISMATCH'
            );
        }

        $view = substr($route, strlen('security/'));
        $view = strtolower(trim($view));

        if (!in_array($view, self::VIEWS, true)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_VIEW_INVALID'
            );
        }

        return $view;
    }

    private function securityUrl(string $locale, string $view): string
    {
        if (!in_array($view, self::VIEWS, true)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_VIEW_INVALID'
            );
        }

        if ($view === 'overview') {
            return $this->routeUrl($locale, 'security');
        }

        return $this->routeUrl(
            $locale,
            'security/' . $view
        );
    }

    private function routeUrl(string $locale, string $route): string
    {
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

    private function redirect(string $locale, string $route): void
    {
        Response::empty(303, [
            'Location' => $this->routeUrl($locale, $route),
        ])->send();
    }
}
