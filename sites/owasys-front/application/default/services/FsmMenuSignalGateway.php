<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\Http\LocalizedRouteResolverInterface;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Csrf\CsrfTokenManager;
use Opus\Security\Csrf\CsrfTokenManagerInterface;

/** Executes user menu commands exclusively through the canonical OWASYS EFSM. */
final class OwasysFsmMenuSignalGateway
{
    private const FSM_SESSION_KEY = 'opus.fsm.owasys-front';
    private const CSRF_SCOPE = 'owasys.fsm.menu';

    private readonly CsrfTokenManagerInterface $csrf;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig,
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security,
        private readonly LocalizedRouteResolverInterface $localizedRoutes,
        private readonly OwasysSessionRuntimeInterface $sessionRuntime,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null,
        ?CsrfTokenManagerInterface $csrf = null
    ) {
        $this->csrf = $csrf ?? new CsrfTokenManager();
    }

    public function handleIfRequested(): bool
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'POST'
            || !array_key_exists('owasys_fsm_signal', $_POST)) {
            return false;
        }

        $this->sessionRuntime->start();
        $identity = $this->session->user();
        if (!is_array($identity)) {
            throw new RuntimeException('OWASYS_FSM_MENU_AUTH_REQUIRED');
        }

        $signal = trim((string) ($_POST['owasys_fsm_signal'] ?? ''));
        if (preg_match('/^[a-z][a-z0-9_]{1,95}$/D', $signal) !== 1) {
            throw new RuntimeException('OWASYS_FSM_MENU_SIGNAL_INVALID');
        }
        $token = trim((string) ($_POST['csrf_token'] ?? ''));
        $this->csrf->assertValid(self::CSRF_SCOPE, $token);

        $fsmConfig = $this->loadFsmConfig();
        $definition = $this->menuSignalDefinition($fsmConfig, $signal);
        $handlers = (new OwasysFsmGuardHandlers($this->security))
            ->forConfig($fsmConfig, $identity);
        $fsm = FsmSiteLoader::processorForSiteRoot(
            $this->siteRoot,
            $handlers,
            $this->profiler,
            $this->parentSpanId
        );
        $store = new FsmSessionStore(self::FSM_SESSION_KEY);
        $store->restore($fsm);

        $currentApp = $this->session->currentApp();
        $context = [
            'identity' => $identity,
            'is_authenticated' => true,
            'roles' => is_array($identity['roles'] ?? null)
                ? array_values(array_filter($identity['roles'], 'is_string'))
                : [],
            'current_app' => $currentApp,
            'has_current_app' => is_array($currentApp),
            'menu_signal' => $signal,
            'menu_resource' => (string) ($definition['resource'] ?? ''),
            'menu_operation' => (string) ($definition['operation'] ?? ''),
            'method' => $method,
            'post' => $_POST,
        ];

        $currentState = $fsm->currentState();
        $inspection = $fsm->inspectTransition(
            $currentState,
            $signal,
            $context
        );
        if (($inspection['transition_found'] ?? false) !== true) {
            http_response_code(409);
            throw new RuntimeException(
                'OWASYS_FSM_MENU_TRANSITION_NOT_FOUND:'
                . $currentState . ':' . $signal
            );
        }
        if (($inspection['enabled'] ?? false) !== true) {
            $failed = array_values(array_filter(
                is_array($inspection['failed_guards'] ?? null)
                    ? $inspection['failed_guards']
                    : [],
                'is_string'
            ));
            $guard = (string) ($failed[0] ?? '');
            http_response_code(str_starts_with($guard, 'acl:') ? 403 : 409);
            throw new RuntimeException(
                'OWASYS_FSM_MENU_GUARD_REFUSED:' . $guard
            );
        }

        $transition = $fsm->transition($currentState, $signal, $context);
        $actions = is_array($transition['actions'] ?? null)
            ? $transition['actions']
            : [];
        if ($actions !== []) {
            (new OwasysFsmActionHandlers(
                $this->session,
                $this->security,
                new OwasysRegistryModel($this->siteRoot, $this->profiler)
            ))->dispatcher()->dispatch($transition, $context);
        }
        $store->persist($fsm);

        $target = (string) ($transition['next_state'] ?? '');
        $state = $fsm->state($target);
        $route = trim((string) ($state['route'] ?? ''));
        if ($route === '') {
            throw new RuntimeException(
                'OWASYS_FSM_MENU_TARGET_ROUTE_MISSING:' . $target
            );
        }
        $url = $this->localizedRoutes->url(
            $this->basePath(),
            $this->requestLocale(),
            $route,
            [
                'operation' => (string) ($definition['operation'] ?? ''),
            ]
        );
        header('Location: ' . $url, true, 303);
        return true;
    }

    /** @return array<string,mixed> */
    private function loadFsmConfig(): array
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
            throw new RuntimeException('OWASYS_FSM_MENU_CONFIG_PATH_INVALID');
        }
        try {
            return StructuredFileLoader::instance()->read(
                $this->siteRoot . '/' . $relative
            );
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_FSM_MENU_CONFIG_INVALID:' . $cause->getMessage(),
                0,
                $cause
            );
        }
    }

    /**
     * @param array<string,mixed> $fsmConfig
     * @return array<string,mixed>
     */
    private function menuSignalDefinition(array $fsmConfig, string $signal): array
    {
        foreach ((array) ($fsmConfig['signals'] ?? []) as $definition) {
            if (!is_array($definition)
                || (string) ($definition['id'] ?? '') !== $signal) {
                continue;
            }
            if (($definition['menu'] ?? false) !== true
                || ($definition['origin'] ?? '') !== 'user'
                || ($definition['type'] ?? '') !== 'command') {
                throw new RuntimeException(
                    'OWASYS_FSM_MENU_SIGNAL_NOT_COMMAND:' . $signal
                );
            }
            return $definition;
        }
        throw new RuntimeException(
            'OWASYS_FSM_MENU_SIGNAL_UNDECLARED:' . $signal
        );
    }

    private function requestLocale(): string
    {
        $path = parse_url(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        );
        $segments = array_values(array_filter(
            explode('/', trim(is_string($path) ? $path : '/', '/')),
            static fn (string $segment): bool => $segment !== ''
        ));
        if (($segments[0] ?? '') === 'owasys') {
            array_shift($segments);
        }
        $candidate = (string) ($segments[0] ?? '');
        $locales = is_array($this->siteConfig['locales'] ?? null)
            ? array_values(array_filter($this->siteConfig['locales'], 'is_string'))
            : [];
        return in_array($candidate, $locales, true)
            ? $candidate
            : (string) ($this->siteConfig['default_locale'] ?? 'fr-FR');
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
}
