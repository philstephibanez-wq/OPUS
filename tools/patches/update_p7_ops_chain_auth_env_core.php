<?php
declare(strict_types=1);

$root = getcwd();
$siteDir = $root . '/sites/opus-p7-ops';
$publicDir = $siteDir . '/public';
$configDir = $siteDir . '/config';
$logDir = $root . '/var/logs/opus_lstsar-manager';

foreach ([$siteDir, $publicDir] as $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, 'P7_CHAIN_DIR_MISSING: ' . $dir . PHP_EOL);
        exit(1);
    }
}

foreach ([$configDir, $logDir] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, 'P7_CHAIN_DIR_CREATE_FAILED: ' . $dir . PHP_EOL);
        exit(1);
    }
}

file_put_contents($root . '/var/logs/.gitignore', "*.log\n**/*.log\n!**/.gitkeep\n");
file_put_contents($logDir . '/.gitkeep', '');

$devHash = password_hash('admin', PASSWORD_DEFAULT);
$devEnv = <<<PHP
<?php
declare(strict_types=1);

return [
    'environment' => 'dev',
    'display_errors' => true,
    'require_auth' => true,
    'auth' => [
        'mode' => 'local',
        'users' => [
            'admin' => [
                'password_hash' => '{$devHash}',
                'roles' => ['ops.admin'],
            ],
        ],
    ],
    'sso' => [
        'enabled' => false,
        'provider' => null,
    ],
    'delivery' => [
        'profile' => 'dev',
        'allow_demo_credentials' => true,
    ],
];

PHP;

$prodEnv = <<<'PHP'
<?php
declare(strict_types=1);

// environment.prod.example.php

return [
    'environment' => 'prod',
    'display_errors' => false,
    'require_auth' => true,
    'auth' => [
        'mode' => 'local',
        'users' => [
            'admin' => [
                'password_hash' => 'CHANGE_ME_WITH_PASSWORD_HASH',
                'roles' => ['ops.admin'],
            ],
        ],
    ],
    'sso' => [
        'enabled' => false,
        'provider' => null,
    ],
    'delivery' => [
        'profile' => 'prod',
        'allow_demo_credentials' => false,
    ],
];

PHP;

if (!is_file($configDir . '/environment.dev.php')) {
    file_put_contents($configDir . '/environment.dev.php', $devEnv);
}

if (!is_file($configDir . '/environment.prod.example.php')) {
    file_put_contents($configDir . '/environment.prod.example.php', $prodEnv);
}

if (!is_file($configDir . '/environment.php')) {
    file_put_contents($configDir . '/environment.php', "<?php\ndeclare(strict_types=1);\n\nreturn require __DIR__ . '/environment.dev.php';\n");
}

$languageFile = $publicDir . '/language.php';
$language = file_get_contents($languageFile);
if ($language === false) {
    fwrite(STDERR, 'P7_CHAIN_LANGUAGE_READ_FAILED' . PHP_EOL);
    exit(1);
}

$runtimeBlock = <<<'PHP'

/** P7_OPS_CHAIN_AUTH_ENV_CORE */
if (!function_exists('p7ops_config_path')) {
    function p7ops_config_path(): string
    {
        return dirname(__DIR__) . '/config/environment.php';
    }
}

if (!function_exists('p7ops_config')) {
    function p7ops_config(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $file = p7ops_config_path();
        if (!is_file($file)) {
            throw new RuntimeException('P7_OPS_ENVIRONMENT_CONFIG_MISSING: ' . $file);
        }

        $loaded = require $file;
        if (!is_array($loaded)) {
            throw new RuntimeException('P7_OPS_ENVIRONMENT_CONFIG_INVALID: ' . $file);
        }

        $environment = (string) ($loaded['environment'] ?? '');
        if (!in_array($environment, ['dev', 'prod'], true)) {
            throw new RuntimeException('P7_OPS_ENVIRONMENT_UNSUPPORTED: ' . $environment);
        }

        $config = $loaded;
        return $config;
    }
}

if (!function_exists('p7ops_environment')) {
    function p7ops_environment(): string
    {
        return (string) (p7ops_config()['environment'] ?? 'dev');
    }
}

if (!function_exists('p7ops_log_root')) {
    function p7ops_log_root(): string
    {
        return dirname(__DIR__, 3) . '/var/logs/opus_lstsar-manager';
    }
}

if (!function_exists('p7ops_ensure_log_root')) {
    function p7ops_ensure_log_root(): void
    {
        $root = p7ops_log_root();
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException('P7_OPS_LOG_DIR_CREATE_FAILED: ' . $root);
        }
    }
}

if (!function_exists('p7ops_log_line')) {
    function p7ops_log_line(string $filename, array $payload): void
    {
        try {
            p7ops_ensure_log_root();
            $line = json_encode(array_merge([
                'ts' => gmdate('c'),
                'app' => 'opus_lstsar-manager',
                'environment' => p7ops_environment(),
            ], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!is_string($line)) {
                $line = '{"ts":"' . gmdate('c') . '","app":"opus_lstsar-manager","event":"json_encode_failed"}';
            }

            file_put_contents(p7ops_log_root() . '/' . $filename, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $exception) {
            error_log('P7_OPS_LOG_WRITE_FAILED: ' . $exception->getMessage());
        }
    }
}

if (!function_exists('p7ops_access_log_once')) {
    function p7ops_access_log_once(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $done = true;
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        p7ops_log_line('access.log', [
            'level' => 'INFO',
            'event' => 'http_request',
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'uri' => $uri,
            'path' => rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '')),
            'query' => (string) (parse_url($uri, PHP_URL_QUERY) ?: ''),
            'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);
    }
}

if (!function_exists('p7ops_profiler_enabled')) {
    function p7ops_profiler_enabled(): bool
    {
        $value = strtolower((string) ($_GET['profiler'] ?? $_GET['profile'] ?? ''));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('p7ops_profiler_start_once')) {
    function p7ops_profiler_start_once(): void
    {
        static $started = false;
        if ($started) {
            return;
        }

        $started = true;
        $GLOBALS['p7ops_profiler_start_microtime'] = microtime(true);
        $GLOBALS['p7ops_profiler_start_memory'] = memory_get_usage(true);
        register_shutdown_function(static function (): void {
            p7ops_profiler_finish_once('shutdown');
        });
    }
}

if (!function_exists('p7ops_profiler_metrics')) {
    function p7ops_profiler_metrics(string $phase = 'panel'): array
    {
        $start = (float) ($GLOBALS['p7ops_profiler_start_microtime'] ?? microtime(true));
        $durationMs = round((microtime(true) - $start) * 1000, 3);
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        return [
            'level' => 'INFO',
            'event' => 'profile_request',
            'phase' => $phase,
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'uri' => $uri,
            'path' => rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '')),
            'status' => http_response_code() ?: 200,
            'duration_ms' => $durationMs,
            'memory_start_bytes' => (int) ($GLOBALS['p7ops_profiler_start_memory'] ?? 0),
            'memory_peak_bytes' => memory_get_peak_usage(true),
        ];
    }
}

if (!function_exists('p7ops_profiler_finish_once')) {
    function p7ops_profiler_finish_once(string $phase = 'finish'): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $done = true;
        p7ops_log_line('profiler.log', p7ops_profiler_metrics($phase));
    }
}

if (!function_exists('p7ops_profiler_panel_html')) {
    function p7ops_profiler_panel_html(array $metrics): string
    {
        if (!p7ops_profiler_enabled()) {
            return '';
        }

        $rows = [
            'Environment' => p7ops_environment(),
            'Status' => (string) ($metrics['status'] ?? ''),
            'Duration' => (string) ($metrics['duration_ms'] ?? '') . ' ms',
            'Peak memory' => (string) ($metrics['memory_peak_bytes'] ?? '') . ' bytes',
            'Access log' => 'var/logs/opus_lstsar-manager/access.log',
            'Profiler log' => 'var/logs/opus_lstsar-manager/profiler.log',
        ];

        $html = '<section class="ops-profiler-panel" data-contract="P7_OPS_CHAIN_AUTH_ENV_CORE"><h2>OPS Profiler</h2><dl>';
        foreach ($rows as $key => $value) {
            $html .= '<dt>' . p7ops_h($key) . '</dt><dd>' . p7ops_h($value) . '</dd>';
        }
        $html .= '</dl></section>';

        return $html;
    }
}

if (!function_exists('p7ops_profiler_output_buffer_once')) {
    function p7ops_profiler_output_buffer_once(): void
    {
        static $started = false;
        if ($started) {
            return;
        }

        $started = true;
        ob_start(static function (string $html): string {
            $metrics = p7ops_profiler_metrics('output');
            p7ops_profiler_finish_once('output');
            $panel = p7ops_profiler_panel_html($metrics);
            if ($panel === '') {
                return $html;
            }

            if (stripos($html, '</body>') !== false) {
                return preg_replace('/<\/body>/i', $panel . '</body>', $html, 1) ?: ($html . $panel);
            }

            return $html . $panel;
        });
    }
}

if (!function_exists('p7ops_session_start_once')) {
    function p7ops_session_start_once(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('OPUSLSTSAROPS');
            session_start();
        }
    }
}

if (!function_exists('p7ops_current_user')) {
    function p7ops_current_user(): ?array
    {
        p7ops_session_start_once();
        $user = $_SESSION['p7ops_user'] ?? null;
        return is_array($user) ? $user : null;
    }
}

if (!function_exists('p7ops_is_signed_in')) {
    function p7ops_is_signed_in(): bool
    {
        return p7ops_current_user() !== null;
    }
}

if (!function_exists('p7ops_sign_in')) {
    function p7ops_sign_in(string $username, string $password): bool
    {
        p7ops_session_start_once();
        $config = p7ops_config();
        $users = $config['auth']['users'] ?? [];
        $user = is_array($users) ? ($users[$username] ?? null) : null;

        if (!is_array($user)) {
            p7ops_log_line('auth.log', ['level' => 'WARNING', 'event' => 'signin_failed', 'reason' => 'unknown_user', 'username' => $username]);
            return false;
        }

        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || $hash === 'CHANGE_ME_WITH_PASSWORD_HASH') {
            p7ops_log_line('auth.log', ['level' => 'ERROR', 'event' => 'signin_disabled', 'reason' => 'password_hash_missing', 'username' => $username]);
            return false;
        }

        if (!password_verify($password, $hash)) {
            p7ops_log_line('auth.log', ['level' => 'WARNING', 'event' => 'signin_failed', 'reason' => 'bad_password', 'username' => $username]);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['p7ops_user'] = [
            'username' => $username,
            'roles' => $user['roles'] ?? [],
            'signed_in_at' => gmdate('c'),
        ];

        p7ops_log_line('auth.log', ['level' => 'INFO', 'event' => 'signin_ok', 'username' => $username]);
        return true;
    }
}

if (!function_exists('p7ops_sign_out')) {
    function p7ops_sign_out(): void
    {
        p7ops_session_start_once();
        $username = (string) (($_SESSION['p7ops_user']['username'] ?? '') ?: '');
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        p7ops_log_line('auth.log', ['level' => 'INFO', 'event' => 'logout', 'username' => $username]);
    }
}

if (!function_exists('p7ops_auth_required')) {
    function p7ops_auth_required(): bool
    {
        return (bool) (p7ops_config()['require_auth'] ?? true);
    }
}

if (!function_exists('p7ops_require_signin')) {
    function p7ops_require_signin(): void
    {
        if (PHP_SAPI === 'cli' || !p7ops_auth_required() || p7ops_is_signed_in()) {
            return;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager');
        header('Location: /opus-lstsar-manager/login?next=' . rawurlencode($uri), true, 302);
        exit;
    }
}

if (!function_exists('p7ops_dependency_chain')) {
    function p7ops_dependency_chain(): array
    {
        return [
            ['id' => 'auth', 'label' => 'Login / Logout / controlled sign-in', 'route' => '/opus-lstsar-manager/login', 'status' => 'available'],
            ['id' => 'sso', 'label' => 'SSO optional provider', 'route' => '/opus-lstsar-manager/sso', 'status' => ((p7ops_config()['sso']['enabled'] ?? false) ? 'enabled' : 'disabled')],
            ['id' => 'rbac', 'label' => 'RBAC / policies', 'route' => '/opus-lstsar-manager/chain', 'status' => 'minimal'],
            ['id' => 'fsm', 'label' => 'FSM', 'route' => '/opus-lstsar-manager/fsm', 'status' => 'linked'],
            ['id' => 'cl', 'label' => 'CL', 'route' => '/opus-lstsar-manager/cl', 'status' => 'linked'],
            ['id' => 'models', 'label' => 'Models registry', 'route' => '/opus-lstsar-manager/models', 'status' => 'linked'],
            ['id' => 'database', 'label' => 'Database + tables', 'route' => '/opus-lstsar-manager/models#database', 'status' => 'linked'],
            ['id' => 'odbc', 'label' => 'ODBC Manager / DSN', 'route' => '/opus-lstsar-manager/odbc-manager', 'status' => 'linked'],
            ['id' => 'lstsar', 'label' => 'LSTSAR operations', 'route' => '/opus-lstsar-manager/operations', 'status' => 'available'],
            ['id' => 'logs', 'label' => 'Logs / profiler / audit', 'route' => '/opus-lstsar-manager/diagnostics?profiler=1', 'status' => 'available'],
        ];
    }
}

if (!function_exists('p7ops_render_shell')) {
    function p7ops_render_shell(string $title, string $body): void
    {
        $user = p7ops_current_user();
        $username = is_array($user) ? (string) ($user['username'] ?? '') : '';
        $authLink = $username !== '' ? '<a class="ops-action-button" href="/opus-lstsar-manager/logout">Logout ' . p7ops_h($username) . '</a>' : '<a class="ops-action-button" href="/opus-lstsar-manager/login">Sign in</a>';

        echo '<!doctype html><html lang="' . p7ops_h(p7ops_language()) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . p7ops_h($title) . '</title><link rel="stylesheet" href="/ops-ui.css"></head><body><main class="ops-shell">';
        echo p7ops_language_selector($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager');
        echo '<section class="ops-panel"><div class="ops-topline"><span class="ops-badge">P7_OPS_CHAIN_AUTH_ENV_CORE</span>' . $authLink . '</div><h1>' . p7ops_h($title) . '</h1><p>Environment: <strong>' . p7ops_h(p7ops_environment()) . '</strong></p></section>';
        echo $body;
        echo '</main></body></html>';
    }
}

p7ops_profiler_start_once();
p7ops_profiler_output_buffer_once();
PHP;

if (!str_contains($language, 'P7_OPS_CHAIN_AUTH_ENV_CORE')) {
    $needle = 'p7ops_i18n_begin();';
    if (str_contains($language, $needle)) {
        $language = str_replace($needle, $runtimeBlock . PHP_EOL . PHP_EOL . $needle, $language);
    } else {
        $language .= PHP_EOL . $runtimeBlock . PHP_EOL;
    }
}

if (file_put_contents($languageFile, $language) === false) {
    fwrite(STDERR, 'P7_CHAIN_LANGUAGE_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$router = <<<'PHP'
<?php
/** P7_OPS_CHAIN_AUTH_ENV_CORE */
declare(strict_types=1);

require_once __DIR__ . '/language.php';

p7ops_access_log_once();
p7ops_profiler_start_once();

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$decodedPath = rawurldecode($rawPath);
$path = $decodedPath === '/' ? '/' : rtrim($decodedPath, '/');

$nativeRoute = p7ops_resolve_native_route($path);
if ($nativeRoute !== null) {
    $_GET['lang'] = (string) $nativeRoute['lang'];
    $_GET['site'] = $_GET['site'] ?? 'site-alpha';
    $path = (string) $nativeRoute['canonical'];
}

$publicFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if ($path !== '/' && is_file($publicFile)) {
    return false;
}

$publicRoutes = [
    '/opus-lstsar-manager/login' => 'login.php',
    '/login' => 'login.php',
    '/opus-lstsar-manager/signin' => 'login.php',
    '/opus-lstsar-manager/sign-in' => 'login.php',
    '/opus-lstsar-manager/logout' => 'logout.php',
    '/logout' => 'logout.php',
];

if (isset($publicRoutes[$path])) {
    require __DIR__ . '/' . $publicRoutes[$path];
    return true;
}

p7ops_require_signin();

$routes = [
    '/opus-lstsar-manager' => 'index.php',
    '/opus-lstsar-manager/operations' => 'index.php',
    '/opus-lstsar-manager/action' => 'action.php',
    '/opus-lstsar-manager/command' => 'command.php',
    '/opus-lstsar-manager/command-center' => 'command.php',
    '/opus-lstsar-manager/navigation' => 'navigation.php',
    '/opus-lstsar-manager/navigation-polish' => 'navigation.php',
    '/opus-lstsar-manager/diagnostics' => 'diagnostics.php',
    '/opus-lstsar-manager/runtime-diagnostics' => 'diagnostics.php',
    '/opus-lstsar-manager/health' => 'health.php',
    '/opus-lstsar-manager/health-hub' => 'health.php',
    '/opus-lstsar-manager/chain' => 'chain.php',
    '/opus-lstsar-manager/dependency-chain' => 'chain.php',
    '/opus-lstsar-manager/fsm' => 'fsm.php',
    '/opus-lstsar-manager/cl' => 'cl.php',
    '/opus-lstsar-manager/models' => 'models.php',
    '/opus-lstsar-manager/odbc-manager' => 'odbc-manager.php',
    '/opus-lstsar-manager/sso' => 'sso.php',
];

require __DIR__ . '/' . ($routes[$path] ?? 'index.php');
return true;
PHP;

file_put_contents($publicDir . '/router.php', $router);

$login = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

p7ops_session_start_once();

$error = '';
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '/opus-lstsar-manager');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (p7ops_sign_in($username, $password)) {
        header('Location: ' . ($next !== '' ? $next : '/opus-lstsar-manager'), true, 302);
        exit;
    }

    $error = 'Sign in refused. Check credentials and environment configuration.';
}

$body = '<section class="ops-panel ops-auth-panel"><h2>Controlled sign-in</h2>';
$body .= '<p>Environment: <strong>' . p7ops_h(p7ops_environment()) . '</strong>. Dev default user is <code>admin</code>; production must provide a real password hash in <code>config/environment.php</code>.</p>';
if ($error !== '') {
    $body .= '<p class="ops-error">' . p7ops_h($error) . '</p>';
}
$body .= '<form method="post" class="ops-form" action="/opus-lstsar-manager/login">';
$body .= '<input type="hidden" name="next" value="' . p7ops_h($next) . '">';
$body .= '<label>Username <input name="username" autocomplete="username" required></label>';
$body .= '<label>Password <input name="password" type="password" autocomplete="current-password" required></label>';
$body .= '<button class="ops-action-button" type="submit">Sign in</button>';
$body .= '</form></section>';

p7ops_render_shell('OPUS OPS Sign in', $body);
PHP;

$logout = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

p7ops_sign_out();
header('Location: /opus-lstsar-manager/login?logout=1', true, 302);
exit;
PHP;

$chain = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$items = p7ops_dependency_chain();
$body = '<section class="ops-panel"><h2>Complete OPS dependency chain</h2><p>Canonical chain: SSO/AuthN → RBAC/policies → FSM → CL → Models → Database/tables → ODBC Manager → LSTSAR → Actions/Audit/Logs/Profiler.</p><div class="ops-chain-grid">';
foreach ($items as $item) {
    $body .= '<article class="ops-chain-card"><h3>' . p7ops_h($item['label']) . '</h3><p>Status: <strong>' . p7ops_h($item['status']) . '</strong></p><a class="ops-action-button" href="' . p7ops_h($item['route']) . '">Open</a></article>';
}
$body .= '</div></section>';

p7ops_render_shell('OPUS OPS Complete Chain', $body);
PHP;

$models = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$body = '<section class="ops-panel"><h2>Models Registry</h2><p>Models expose the database, tables, source model and destination model used by LSTSAR.</p>';
$body .= '<div class="ops-chain-grid">';
$body .= '<article class="ops-chain-card" id="database"><h3>Database</h3><p><code>source_dsn</code> / <code>destination_dsn</code></p></article>';
$body .= '<article class="ops-chain-card"><h3>Tables</h3><p><code>orders_source</code> → <code>orders_destination</code></p></article>';
$body .= '<article class="ops-chain-card"><h3>Source model</h3><p><code>source_orders_model</code></p></article>';
$body .= '<article class="ops-chain-card"><h3>Destination model</h3><p><code>destination_orders_model</code></p></article>';
$body .= '</div><p><a class="ops-action-button" href="/opus-lstsar-manager/odbc-manager">Open ODBC Manager</a> <a class="ops-action-button" href="/opus-lstsar-manager/operations">Open LSTSAR</a></p></section>';

p7ops_render_shell('OPUS OPS Models', $body);
PHP;

$odbc = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$body = '<section class="ops-panel"><h2>ODBC Manager</h2><p>ODBC Manager links DSN, driver and connection tests used by source/destination models.</p>';
$body .= '<div class="ops-chain-grid">';
$body .= '<article class="ops-chain-card"><h3>Source DSN</h3><p><code>source_dsn</code></p><p>Driver: <code>odbc</code></p></article>';
$body .= '<article class="ops-chain-card"><h3>Destination DSN</h3><p><code>destination_dsn</code></p><p>Driver: <code>odbc</code></p></article>';
$body .= '<article class="ops-chain-card"><h3>Connection tests</h3><p>Status: explicit check pending</p></article>';
$body .= '</div><p><a class="ops-action-button" href="/opus-lstsar-manager/models">Open Models</a> <a class="ops-action-button" href="/opus-lstsar-manager/diagnostics?profiler=1">Open Logs / Profiler</a></p></section>';

p7ops_render_shell('OPUS OPS ODBC Manager', $body);
PHP;

$fsm = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$body = '<section class="ops-panel"><h2>FSM</h2><p>FSM controls operation state before CL and LSTSAR execution.</p><p><a class="ops-action-button" href="/opus-lstsar-manager/chain">Back to chain</a></p></section>';
p7ops_render_shell('OPUS OPS FSM', $body);
PHP;

$cl = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$body = '<section class="ops-panel"><h2>CL</h2><p>CL links command/orchestration contracts to FSM, Models and LSTSAR actions.</p><p><a class="ops-action-button" href="/opus-lstsar-manager/command-center">Open Command Center</a></p></section>';
p7ops_render_shell('OPUS OPS CL', $body);
PHP;

$sso = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$config = p7ops_config();
$enabled = ($config['sso']['enabled'] ?? false) ? 'enabled' : 'disabled';
$body = '<section class="ops-panel"><h2>SSO</h2><p>SSO is optional and currently <strong>' . p7ops_h($enabled) . '</strong>.</p><p>No silent fallback: production must explicitly configure the SSO provider or local auth password hash.</p></section>';
p7ops_render_shell('OPUS OPS SSO', $body);
PHP;

file_put_contents($publicDir . '/login.php', $login);
file_put_contents($publicDir . '/logout.php', $logout);
file_put_contents($publicDir . '/chain.php', $chain);
file_put_contents($publicDir . '/models.php', $models);
file_put_contents($publicDir . '/odbc-manager.php', $odbc);
file_put_contents($publicDir . '/fsm.php', $fsm);
file_put_contents($publicDir . '/cl.php', $cl);
file_put_contents($publicDir . '/sso.php', $sso);

$cssFile = $publicDir . '/ops-ui.css';
$css = is_file($cssFile) ? (string) file_get_contents($cssFile) : '';
if (!str_contains($css, 'P7_OPS_CHAIN_AUTH_ENV_CORE')) {
    $css .= PHP_EOL;
    $css .= '/* P7_OPS_CHAIN_AUTH_ENV_CORE */' . PHP_EOL;
    $css .= '.ops-chain-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:1rem;min-width:0}.ops-chain-card{min-width:0;border:1px solid rgba(96,165,250,.35);border-radius:1rem;padding:1rem;background:rgba(2,6,23,.55)}.ops-chain-card code,.ops-table code,.ops-kv code{overflow-wrap:anywhere;word-break:normal}.ops-form{display:grid;gap:1rem;max-width:28rem}.ops-form label{display:grid;gap:.35rem}.ops-form input{border:1px solid rgba(96,165,250,.35);border-radius:.75rem;padding:.75rem;background:#020617;color:#f8fafc}.ops-error{color:#fca5a5;font-weight:800}.ops-topline{display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap}.ops-profiler-panel{position:fixed;right:1rem;bottom:1rem;z-index:1200;max-width:28rem;border:1px solid rgba(34,211,238,.45);border-radius:1rem;background:rgba(2,6,23,.96);padding:1rem;box-shadow:0 16px 40px rgba(0,0,0,.35);font-size:.85rem}.ops-profiler-panel dl{display:grid;grid-template-columns:auto 1fr;gap:.35rem .8rem}.ops-profiler-panel dt{font-weight:800;color:#67e8f9}.ops-profiler-panel dd{margin:0;overflow-wrap:anywhere}.ops-table{table-layout:auto}.ops-table td,.ops-table th{vertical-align:top}.ops-table code,[class*="summary"] code{white-space:normal;overflow-wrap:anywhere;word-break:normal}@media (max-width:900px){.ops-profiler-panel{position:static;margin:1rem}.ops-chain-grid{grid-template-columns:1fr}}' . PHP_EOL;
}
file_put_contents($cssFile, $css);

$readmeFile = $siteDir . '/README.md';
$readme = is_file($readmeFile) ? (string) file_get_contents($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;
if (!str_contains($readme, 'P7_OPS_CHAIN_AUTH_ENV_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_CHAIN_AUTH_ENV_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Adds controlled login/logout/sign-in for OPS pages.' . PHP_EOL;
    $readme .= '- Adds minimum environment management with `config/environment.dev.php`, `config/environment.prod.example.php` and active `config/environment.php`.' . PHP_EOL;
    $readme .= '- Adds the full dependency chain: SSO/AuthN, RBAC, FSM, CL, Models, Database/tables, ODBC Manager, LSTSAR, logs/profiler.' . PHP_EOL;
    $readme .= '- Adds `access.log`, `auth.log` and `profiler.log` under `var/logs/opus_lstsar-manager`.' . PHP_EOL;
    $readme .= '- Dev login default: `admin` / `admin`; production must replace the password hash explicitly.' . PHP_EOL;
}
file_put_contents($readmeFile, $readme);

echo 'P7_OPS_CHAIN_AUTH_ENV_CORE_UPDATED' . PHP_EOL;
