<?php
declare(strict_types=1);

use Opus\Fsm\FsmSiteLoader;

final class OwasysRuntimeController
{
    public function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig,
        private readonly OwasysRuntimeUserStore $users,
        private readonly OwasysAuthSession $session
    ) {
    }

    public function run(): void
    {
        $this->startSession();
        [$locale, $routeKey] = $this->resolveRequest();
        $messages = $this->loadMessages($locale);
        $t = static fn (string $key): string => is_string($messages[$key] ?? null) ? $messages[$key] : $key;

        $fsm = FsmSiteLoader::processorForSiteRoot($this->siteRoot);
        $stateKey = 'opus_fsm_state_owasys';
        $currentState = trim((string) ($_SESSION[$stateKey] ?? $fsm->initialState()));

        if ($routeKey === 'logout') {
            $transition = $fsm->transition($currentState, 'logout');
            $this->session->clear();
            $_SESSION[$stateKey] = (string) $transition['to_state'];
            $this->redirect($locale, 'login');
        }

        if ($routeKey === 'login') {
            $this->handleLogin($locale, $t, $fsm, $stateKey, $currentState);
            return;
        }

        if ($routeKey === 'account/password') {
            $this->requireAuthentication($locale);
            $this->handlePasswordChange($locale, $t, $fsm, $stateKey, $currentState);
            return;
        }

        $this->requireAuthentication($locale);
        $user = $this->session->user();
        if (($user['must_change_password'] ?? false) === true) {
            $this->redirect($locale, 'account/password');
        }

        $signal = $this->resolveSignal($locale, $routeKey);
        if ($signal === '') {
            $this->fail(404, 'OWASYS_ROUTE_NOT_FOUND');
        }

        $context = [
            'current_app' => $_SESSION['owasys_current_app'] ?? null,
            'has_current_app' => is_array($_SESSION['owasys_current_app'] ?? null),
        ];

        try {
            $transition = $fsm->transition($currentState, $signal, $context);
        } catch (Throwable $error) {
            $this->fail(409, 'OWASYS_FSM_TRANSITION_REJECTED:' . $error->getMessage());
        }

        $targetState = (string) ($transition['to_state'] ?? '');
        $_SESSION[$stateKey] = $targetState;

        $state = $fsm->state($targetState);
        $module = (string) ($state['module'] ?? $targetState);
        $viewFile = $this->siteRoot . '/application/' . $module . '/Views/index.php';

        if (!is_file($viewFile)) {
            $this->renderPendingModule($locale, $t, $module);
            return;
        }

        $this->renderView($locale, $t, $viewFile, []);
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $auth = is_array($this->siteConfig['auth'] ?? null) ? $this->siteConfig['auth'] : [];
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
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $segments = trim($path, '/') === '' ? [] : explode('/', trim($path, '/'));

        if (($segments[0] ?? '') === 'owasys') {
            array_shift($segments);
        }

        $locales = array_values(array_filter((array) ($this->siteConfig['locales'] ?? []), 'is_string'));
        $defaultLocale = (string) ($this->siteConfig['default_locale'] ?? 'fr');
        $locale = (string) ($segments[0] ?? $defaultLocale);

        if (in_array($locale, $locales, true)) {
            array_shift($segments);
        } else {
            $locale = $defaultLocale;
        }

        $routeKey = implode('/', $segments);

        return [$locale, $routeKey === '' ? 'login' : $routeKey];
    }

    private function handleLogin(string $locale, callable $t, object $fsm, string $stateKey, string $currentState): void
    {
        if ($this->session->isAuthenticated()) {
            $user = $this->session->user();
            $this->redirect(
                $locale,
                (($user['must_change_password'] ?? false) === true) ? 'account/password' : 'applications'
            );
        }

        require_once $this->siteRoot . '/application/login/Models/LoginModel.php';
        require_once $this->siteRoot . '/application/login/Controllers/LoginController.php';

        $controller = new OwasysLoginController(new OwasysLoginModel($this->users, $this->session));
        $result = $controller->handle((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), $_POST);

        if (($result['ok'] ?? false) === true) {
            $event = (($this->session->user()['must_change_password'] ?? false) === true)
                ? 'password_change_required'
                : 'login_success';

            $transition = $fsm->transition(
                $currentState,
                $event,
                ['must_change_password' => $event === 'password_change_required']
            );

            $_SESSION[$stateKey] = (string) $transition['to_state'];
            $this->redirect($locale, ltrim((string) $result['redirect'], '/'));
        }

        $this->renderView(
            $locale,
            $t,
            $this->siteRoot . '/application/login/Views/index.php',
            ['error' => $result['error'] ?? null]
        );
    }

    private function handlePasswordChange(string $locale, callable $t, object $fsm, string $stateKey, string $currentState): void
    {
        require_once $this->siteRoot . '/application/account/Models/PasswordModel.php';
        require_once $this->siteRoot . '/application/account/Controllers/PasswordController.php';

        $minimum = (int) ($this->siteConfig['auth']['minimum_password_length'] ?? 10);
        $controller = new OwasysPasswordController(
            new OwasysPasswordModel($this->users, $this->session, $minimum)
        );
        $result = $controller->handle((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), $_POST);

        if (($result['ok'] ?? false) === true) {
            $transition = $fsm->transition($currentState, 'password_changed');
            $_SESSION[$stateKey] = (string) $transition['to_state'];
            $this->redirect($locale, ltrim((string) $result['redirect'], '/'));
        }

        $this->renderView(
            $locale,
            $t,
            $this->siteRoot . '/application/account/Views/index.php',
            ['error' => $result['error'] ?? null]
        );
    }

    private function requireAuthentication(string $locale): void
    {
        if (!$this->session->isAuthenticated()) {
            $this->redirect($locale, 'login');
        }
    }

    private function resolveSignal(string $locale, string $routeKey): string
    {
        $routes = json_decode((string) file_get_contents($this->siteRoot . '/config/routes.json'), true);

        return is_array($routes) && is_array($routes['routes'][$locale] ?? null)
            ? trim((string) ($routes['routes'][$locale][$routeKey] ?? ''))
            : '';
    }

    /** @return array<string,string> */
    private function loadMessages(string $locale): array
    {
        $fallbackFile = $this->siteRoot . '/application/default/local/en.php';
        $localeFile = $this->siteRoot . '/application/default/local/' . $locale . '.php';
        $fallback = is_file($fallbackFile) ? require $fallbackFile : [];
        $primary = is_file($localeFile) ? require $localeFile : [];

        return array_replace(is_array($fallback) ? $fallback : [], is_array($primary) ? $primary : []);
    }

    /** @param array<string,mixed> $variables */
    private function renderView(string $locale, callable $t, string $viewFile, array $variables): void
    {
        extract($variables, EXTR_SKIP);
        require $viewFile;
    }

    private function renderPendingModule(string $locale, callable $t, string $module): void
    {
        http_response_code(501);
        header('Content-Type: text/html; charset=UTF-8');
        $title = htmlspecialchars($t('menu.' . $module), ENT_QUOTES, 'UTF-8');
        $moduleEscaped = htmlspecialchars($module, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="' . htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') . '">';
        echo '<head><meta charset="utf-8"><title>' . $title . '</title></head>';
        echo '<body><main><h1>' . $title . '</h1><p>OWASYS_MODULE_PENDING:' . $moduleEscaped . '</p></main></body></html>';
    }

    private function redirect(string $locale, string $route): never
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $directory = rtrim(dirname($script), '/');
        $base = $directory === '/' ? '' : $directory;

        header('Location: ' . $base . '/' . rawurlencode($locale) . '/' . ltrim($route, '/'), true, 303);
        exit;
    }

    private function fail(int $status, string $message): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        exit($message);
    }
}
