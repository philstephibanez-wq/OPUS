<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Profiler\ProfilerInterface;

/** Read-only security workspace for the currently selected OPUS application. */
final class OwasysSecurityController
{
    private const FSM_SESSION_KEY = 'opus.fsm.owasys-front';
    private const VIEWS = [
        'identities',
        'roles',
        'permissions',
        'assignments',
        'resources',
    ];

    private readonly OwasysLocaleRegistry $locales;
    private readonly OwasysNavigationBuilder $navigation;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig,
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security,
        private readonly OwasysScorePageRenderer $renderer,
        private readonly OwasysSessionRuntimeInterface $sessionRuntime,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null
    ) {
        $this->locales = new OwasysLocaleRegistry($siteConfig);
        $this->navigation = new OwasysNavigationBuilder($security);
    }

    public function matchesCurrentRequest(): bool
    {
        [, $route] = $this->resolveRequest();
        return $route === 'security';
    }

    public function run(): void
    {
        $this->sessionRuntime->start();
        [$locale, $route] = $this->resolveRequest();
        if ($route !== 'security') {
            throw new RuntimeException('OWASYS_SECURITY_ROUTE_MISMATCH');
        }

        $identity = $this->session->user();
        if (!is_array($identity)) {
            $this->redirect($locale, 'login');
        }
        $currentApp = $this->session->currentApp();
        if (!is_array($currentApp)) {
            $this->redirect($locale, 'applications');
        }

        $this->security->assertAllowed($identity, 'security', 'open');
        $fsmConfig = $this->fsmConfig();
        $fsm = FsmSiteLoader::processorForSiteRoot(
            $this->siteRoot,
            [],
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
        $snapshot = $this->security->securitySnapshot($identity, $siteId);
        $view = strtolower(trim((string) ($_GET['view'] ?? 'identities')));
        if (!in_array($view, self::VIEWS, true)) {
            throw new RuntimeException('OWASYS_SECURITY_VIEW_INVALID');
        }

        $this->render(
            $fsmConfig,
            $state,
            $locale,
            $identity,
            $currentApp,
            $snapshot,
            $view
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
     */
    private function render(
        array $fsmConfig,
        string $state,
        string $locale,
        array $identity,
        array $currentApp,
        array $snapshot,
        string $view
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
                true,
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
                'read_only' => true,
                'view_identities' => $view === 'identities',
                'view_roles' => $view === 'roles',
                'view_permissions' => $view === 'permissions',
                'view_assignments' => $view === 'assignments',
                'view_resources' => $view === 'resources',
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
            ],
            'providers' => $this->normalizeProviders(
                $this->rows($snapshot, 'providers')
            ),
            'identities' => $this->normalizeIdentities(
                $this->rows($snapshot, 'identities')
            ),
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
            throw new RuntimeException(
                'OWASYS_SECURITY_FSM_PATH_INVALID'
            );
        }
        return StructuredFileLoader::instance()->read(
            $this->siteRoot . '/' . $relative
        );
    }

    private function securityUrl(string $locale, string $view): string
    {
        return $this->routeUrl($locale, 'security')
            . '?'
            . http_build_query(
                ['view' => $view],
                '',
                '&',
                PHP_QUERY_RFC3986
            );
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

    private function redirect(string $locale, string $route): never
    {
        header(
            'Location: ' . $this->routeUrl($locale, $route),
            true,
            303
        );
        exit;
    }
}
