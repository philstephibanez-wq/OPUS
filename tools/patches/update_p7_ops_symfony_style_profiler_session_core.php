<?php
declare(strict_types=1);

$root = getcwd();
$siteDir = $root . '/sites/opus-p7-ops';
$publicDir = $siteDir . '/public';
$configDir = $siteDir . '/config';
$logDir = $root . '/var/logs/opus_lstsar-manager';

function p7sf_read(string $file): string
{
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, 'P7_SF_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }

    return $source;
}

function p7sf_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'P7_SF_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

foreach ([$siteDir, $publicDir, $configDir] as $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, 'P7_SF_DIR_MISSING: ' . $dir . PHP_EOL);
        exit(1);
    }
}

if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    fwrite(STDERR, 'P7_SF_LOG_DIR_CREATE_FAILED: ' . $logDir . PHP_EOL);
    exit(1);
}

file_put_contents($root . '/var/logs/.gitignore', "*.log\n**/*.log\n!**/.gitkeep\n");
file_put_contents($logDir . '/.gitkeep', '');

$prod = $configDir . '/environment.prod.example.php';
if (is_file($prod)) {
    $prodSource = p7sf_read($prod);
    if (!str_contains($prodSource, 'environment.prod.example.php')) {
        $prodSource = str_replace(
            "declare(strict_types=1);\n",
            "declare(strict_types=1);\n\n// environment.prod.example.php\n",
            $prodSource
        );
        p7sf_write($prod, $prodSource);
    }
}

$languageFile = $publicDir . '/language.php';
$language = p7sf_read($languageFile);

$language = str_replace("\$GLOBALS['p7ops_profiler_start_ns'] = hrtime(true);", "\$GLOBALS['p7ops_profiler_start_microtime'] = microtime(true);", $language);
$language = str_replace('p7ops_profiler_start_ns', 'p7ops_profiler_start_microtime', $language);
$language = str_replace(
    '$start = (int) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));',
    '$start = (float) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));',
    $language
);
$language = str_replace(
    '$start = (int) ($GLOBALS[\'p7ops_profiler_start_ns\'] ?? hrtime(true));',
    '$start = (float) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));',
    $language
);
$language = str_replace('round((hrtime(true) - $start) / 1000000, 3)', 'round((microtime(true) - $start) * 1000, 3)', $language);
$language = str_replace('round((microtime(true) - $start) / 1000000, 3)', 'round((microtime(true) - $start) * 1000, 3)', $language);

$symfonyProfilerBlock = <<<'PHP'

/** P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE */
if (!function_exists('p7ops_sf_h')) {
    function p7ops_sf_h(string $value): string
    {
        if (function_exists('p7ops_h')) {
            return p7ops_h($value);
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('p7ops_sf_session_start_once')) {
    function p7ops_sf_session_start_once(): void
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

if (!function_exists('p7ops_sf_profiler_handle_toggle')) {
    function p7ops_sf_profiler_handle_toggle(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        p7ops_sf_session_start_once();

        $value = strtolower((string) ($_GET['profiler'] ?? ''));
        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            $_SESSION['p7ops_sf_profiler_enabled'] = true;
        }

        if (in_array($value, ['0', 'false', 'no', 'off', 'exit'], true)) {
            unset($_SESSION['p7ops_sf_profiler_enabled']);
        }
    }
}

if (!function_exists('p7ops_sf_profiler_enabled')) {
    function p7ops_sf_profiler_enabled(): bool
    {
        if (PHP_SAPI === 'cli') {
            return (bool) ($_GET['profiler'] ?? false);
        }

        p7ops_sf_profiler_handle_toggle();
        return (bool) ($_SESSION['p7ops_sf_profiler_enabled'] ?? false);
    }
}

if (!function_exists('p7ops_sf_profiler_chain')) {
    function p7ops_sf_profiler_chain(): array
    {
        return [
            ['key' => 'auth', 'label' => '01 AuthN / Login / Logout / SSO optional', 'route' => '/opus-lstsar-manager/login', 'status' => 'active'],
            ['key' => 'session', 'label' => '02 Session / RBAC / policies', 'route' => '/opus-lstsar-manager/profiler#session', 'status' => 'minimal'],
            ['key' => 'fsm', 'label' => '03 FSM state control', 'route' => '/opus-lstsar-manager/fsm', 'status' => 'linked'],
            ['key' => 'cl', 'label' => '04 CL command layer', 'route' => '/opus-lstsar-manager/cl', 'status' => 'linked'],
            ['key' => 'models', 'label' => '05 Models registry', 'route' => '/opus-lstsar-manager/models', 'status' => 'linked'],
            ['key' => 'database', 'label' => '06 Database + tables + columns', 'route' => '/opus-lstsar-manager/models#database', 'status' => 'linked'],
            ['key' => 'odbc', 'label' => '07 ODBC Manager / DSN / connection tests', 'route' => '/opus-lstsar-manager/odbc-manager', 'status' => 'linked'],
            ['key' => 'lstsar', 'label' => '08 LSTSAR Load / Secure / Transform / Store', 'route' => '/opus-lstsar-manager/operations', 'status' => 'active'],
            ['key' => 'actions', 'label' => '09 Actions / preview / dry-run / audit', 'route' => '/opus-lstsar-manager/command-center', 'status' => 'active'],
            ['key' => 'observability', 'label' => '10 Logs / profiler / diagnostics', 'route' => '/opus-lstsar-manager/profiler', 'status' => 'active'],
        ];
    }
}

if (!function_exists('p7ops_sf_profiler_request_token')) {
    function p7ops_sf_profiler_request_token(): string
    {
        if (!isset($GLOBALS['p7ops_sf_profiler_token'])) {
            $GLOBALS['p7ops_sf_profiler_token'] = substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
        }

        return (string) $GLOBALS['p7ops_sf_profiler_token'];
    }
}

if (!function_exists('p7ops_sf_profiler_collect')) {
    function p7ops_sf_profiler_collect(string $phase = 'collect'): array
    {
        $start = (float) ($GLOBALS['p7ops_sf_profiler_start_microtime'] ?? microtime(true));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: ''));

        $user = null;
        if (function_exists('p7ops_current_user')) {
            $user = p7ops_current_user();
        }

        $sessionEnabled = false;
        if (PHP_SAPI !== 'cli') {
            p7ops_sf_session_start_once();
            $sessionEnabled = (bool) ($_SESSION['p7ops_sf_profiler_enabled'] ?? false);
        }

        return [
            'token' => p7ops_sf_profiler_request_token(),
            'phase' => $phase,
            'environment' => function_exists('p7ops_environment') ? p7ops_environment() : 'dev',
            'status' => http_response_code() ?: 200,
            'duration_ms' => round((microtime(true) - $start) * 1000, 3),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'memory_current_bytes' => memory_get_usage(true),
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'uri' => $uri,
            'path' => $path,
            'query' => (string) (parse_url($uri, PHP_URL_QUERY) ?: ''),
            'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'session_id' => PHP_SAPI === 'cli' ? 'cli' : session_id(),
            'session_profiler' => $sessionEnabled ? 'enabled' : 'disabled',
            'user' => is_array($user) ? (string) ($user['username'] ?? 'signed-in') : 'anonymous',
            'roles' => is_array($user) ? implode(', ', array_map('strval', (array) ($user['roles'] ?? []))) : '',
            'chain' => p7ops_sf_profiler_chain(),
            'logs' => [
                'access' => 'var/logs/opus_lstsar-manager/access.log',
                'auth' => 'var/logs/opus_lstsar-manager/auth.log',
                'profiler' => 'var/logs/opus_lstsar-manager/profiler.log',
                'php_server' => 'var/logs/opus_lstsar-manager/php-server.log',
            ],
        ];
    }
}

if (!function_exists('p7ops_sf_profiler_store')) {
    function p7ops_sf_profiler_store(array $profile): void
    {
        if (PHP_SAPI !== 'cli') {
            p7ops_sf_session_start_once();
            $_SESSION['p7ops_sf_profiler_history'] = array_values(array_slice((array) ($_SESSION['p7ops_sf_profiler_history'] ?? []), -19));
            $_SESSION['p7ops_sf_profiler_history'][] = $profile;
        }

        if (function_exists('p7ops_log_line')) {
            p7ops_log_line('profiler.log', [
                'level' => 'INFO',
                'event' => 'symfony_style_profile',
                'token' => (string) ($profile['token'] ?? ''),
                'method' => (string) ($profile['method'] ?? ''),
                'uri' => (string) ($profile['uri'] ?? ''),
                'status' => (int) ($profile['status'] ?? 200),
                'duration_ms' => (float) ($profile['duration_ms'] ?? 0),
                'memory_peak_bytes' => (int) ($profile['memory_peak_bytes'] ?? 0),
            ]);
        }
    }
}

if (!function_exists('p7ops_sf_profiler_toolbar_html')) {
    function p7ops_sf_profiler_toolbar_html(array $profile): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager');
        $exit = '/opus-lstsar-manager/profiler/exit?next=' . rawurlencode($uri);
        $profileUrl = '/opus-lstsar-manager/profiler?token=' . rawurlencode((string) ($profile['token'] ?? 'latest'));

        return '<div class="sf-toolbar p7ops-sf-toolbar" data-contract="P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE">'
            . '<a class="sf-toolbar-block sf-toolbar-main" href="' . p7ops_sf_h($profileUrl) . '">OPUS Profiler <strong>' . p7ops_sf_h((string) ($profile['token'] ?? '')) . '</strong></a>'
            . '<span class="sf-toolbar-block">Env <strong>' . p7ops_sf_h((string) ($profile['environment'] ?? '')) . '</strong></span>'
            . '<span class="sf-toolbar-block">HTTP <strong>' . p7ops_sf_h((string) ($profile['status'] ?? '')) . '</strong></span>'
            . '<span class="sf-toolbar-block">Time <strong>' . p7ops_sf_h((string) ($profile['duration_ms'] ?? '')) . ' ms</strong></span>'
            . '<span class="sf-toolbar-block">Memory <strong>' . p7ops_sf_h((string) ($profile['memory_peak_bytes'] ?? '')) . '</strong></span>'
            . '<a class="sf-toolbar-block" href="/opus-lstsar-manager/chain">OPS Chain</a>'
            . '<a class="sf-toolbar-block" href="/opus-lstsar-manager/models">Models</a>'
            . '<a class="sf-toolbar-block" href="/opus-lstsar-manager/odbc-manager">ODBC</a>'
            . '<a class="sf-toolbar-block sf-toolbar-exit" href="' . p7ops_sf_h($exit) . '">Exit profiler</a>'
            . '</div>';
    }
}

if (!function_exists('p7ops_sf_profiler_boot_once')) {
    function p7ops_sf_profiler_boot_once(): void
    {
        static $booted = false;
        if ($booted || PHP_SAPI === 'cli') {
            return;
        }

        $booted = true;
        p7ops_sf_profiler_handle_toggle();

        if (!p7ops_sf_profiler_enabled()) {
            return;
        }

        $GLOBALS['p7ops_sf_profiler_start_microtime'] = microtime(true);
        p7ops_sf_profiler_request_token();

        ob_start(static function (string $html): string {
            $profile = p7ops_sf_profiler_collect('toolbar');
            p7ops_sf_profiler_store($profile);
            $toolbar = p7ops_sf_profiler_toolbar_html($profile);

            if (stripos($html, '</body>') !== false) {
                return preg_replace('/<\/body>/i', $toolbar . '</body>', $html, 1) ?: ($html . $toolbar);
            }

            return $html . $toolbar;
        });
    }
}

if (!function_exists('p7ops_sf_profiler_history')) {
    function p7ops_sf_profiler_history(): array
    {
        if (PHP_SAPI === 'cli') {
            return [];
        }

        p7ops_sf_session_start_once();
        return array_values((array) ($_SESSION['p7ops_sf_profiler_history'] ?? []));
    }
}

if (!function_exists('p7ops_sf_profiler_disable')) {
    function p7ops_sf_profiler_disable(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        p7ops_sf_session_start_once();
        unset($_SESSION['p7ops_sf_profiler_enabled']);
    }
}
PHP;

if (!str_contains($language, 'P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE')) {
    $language .= PHP_EOL . $symfonyProfilerBlock . PHP_EOL;
}

p7sf_write($languageFile, $language);

$routerFile = $publicDir . '/router.php';
$router = p7sf_read($routerFile);

if (!str_contains($router, 'p7ops_sf_profiler_boot_once();')) {
    $needle = "p7ops_profiler_start_once();";
    if (str_contains($router, $needle)) {
        $router = str_replace($needle, $needle . PHP_EOL . 'p7ops_sf_profiler_boot_once();', $router);
    } else {
        $require = "require_once __DIR__ . '/language.php';";
        $router = str_replace($require, $require . PHP_EOL . 'p7ops_sf_profiler_boot_once();', $router);
    }
}

if (!str_contains($router, "'/opus-lstsar-manager/profiler'")) {
    $router = str_replace(
        "'/opus-lstsar-manager/sso' => 'sso.php',",
        "'/opus-lstsar-manager/sso' => 'sso.php'," . PHP_EOL
        . "    '/opus-lstsar-manager/profiler' => 'profiler.php'," . PHP_EOL
        . "    '/opus-lstsar-manager/profiler/exit' => 'profiler-exit.php'," . PHP_EOL
        . "    '/_profiler' => 'profiler.php'," . PHP_EOL
        . "    '/_profiler/exit' => 'profiler-exit.php',",
        $router
    );
}

p7sf_write($routerFile, $router);

$profilerPage = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

p7ops_sf_session_start_once();
$history = p7ops_sf_profiler_history();
$current = $history !== [] ? $history[array_key_last($history)] : p7ops_sf_profiler_collect('page');

$panel = static function (string $id, string $title, string $html): string {
    return '<article id="' . p7ops_sf_h($id) . '" class="sf-profiler-panel"><h2>' . p7ops_sf_h($title) . '</h2>' . $html . '</article>';
};

$kv = static function (array $rows): string {
    $out = '<dl class="sf-profiler-kv">';
    foreach ($rows as $key => $value) {
        $out .= '<dt>' . p7ops_sf_h((string) $key) . '</dt><dd>' . p7ops_sf_h((string) $value) . '</dd>';
    }
    return $out . '</dl>';
};

$chainHtml = '<ol class="ops-chain-flow">';
foreach (p7ops_sf_profiler_chain() as $step) {
    $chainHtml .= '<li class="ops-chain-step"><a href="' . p7ops_sf_h((string) $step['route']) . '"><strong>' . p7ops_sf_h((string) $step['label']) . '</strong><span>' . p7ops_sf_h((string) $step['status']) . '</span></a></li>';
}
$chainHtml .= '</ol>';

$logsHtml = '<ul class="sf-profiler-log-list">';
foreach ((array) ($current['logs'] ?? []) as $name => $path) {
    $logsHtml .= '<li><strong>' . p7ops_sf_h((string) $name) . '</strong> <code>' . p7ops_sf_h((string) $path) . '</code></li>';
}
$logsHtml .= '</ul>';

$body = '<section class="sf-profiler-page" data-contract="P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE">';
$body .= '<div class="sf-profiler-hero"><div><h1>OPUS Web Profiler</h1><p>Symfony-style typed profiler. Enabled in session until explicit exit.</p></div><div class="sf-profiler-actions"><a class="ops-action-button" href="/opus-lstsar-manager?profiler=1">Enable</a><a class="ops-action-button" href="/opus-lstsar-manager/profiler/exit">Exit profiler</a></div></div>';
$body .= '<nav class="sf-profiler-tabs"><a href="#request">Request</a><a href="#performance">Performance</a><a href="#session">Session</a><a href="#auth">Auth</a><a href="#chain">OPS Chain</a><a href="#logs">Logs</a><a href="#config">Config</a></nav>';
$body .= '<div class="sf-profiler-grid">';
$body .= $panel('request', 'Request', $kv([
    'Token' => $current['token'] ?? '',
    'Method' => $current['method'] ?? '',
    'URI' => $current['uri'] ?? '',
    'Path' => $current['path'] ?? '',
    'Query' => $current['query'] ?? '',
    'Remote address' => $current['remote_addr'] ?? '',
]));
$body .= $panel('performance', 'Performance', $kv([
    'Status' => $current['status'] ?? '',
    'Duration' => (string) ($current['duration_ms'] ?? '') . ' ms',
    'Peak memory' => (string) ($current['memory_peak_bytes'] ?? '') . ' bytes',
    'Current memory' => (string) ($current['memory_current_bytes'] ?? '') . ' bytes',
]));
$body .= $panel('session', 'Session', $kv([
    'Session id' => $current['session_id'] ?? '',
    'Profiler mode' => $current['session_profiler'] ?? '',
    'Persistence' => 'SESSION p7ops_sf_profiler_enabled',
    'Exit route' => '/opus-lstsar-manager/profiler/exit',
]));
$body .= $panel('auth', 'Auth / SSO', $kv([
    'User' => $current['user'] ?? '',
    'Roles' => $current['roles'] ?? '',
    'SSO' => ((p7ops_config()['sso']['enabled'] ?? false) ? 'enabled' : 'disabled'),
    'Environment' => $current['environment'] ?? '',
]));
$body .= $panel('chain', 'FSM + CL + Models + ODBC + LSTSAR', $chainHtml);
$body .= $panel('logs', 'Logs', $logsHtml);
$body .= $panel('config', 'Environment', $kv([
    'Config' => 'sites/opus-p7-ops/config/environment.php',
    'Dev profile' => 'sites/opus-p7-ops/config/environment.dev.php',
    'Prod example' => 'sites/opus-p7-ops/config/environment.prod.example.php',
    'No silent fallback' => 'true',
]));
$body .= '</div></section>';

p7ops_render_shell('OPUS Web Profiler', $body);
PHP;

$profilerExit = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

p7ops_sf_profiler_disable();
$next = (string) ($_GET['next'] ?? '/opus-lstsar-manager');
header('Location: ' . ($next !== '' ? $next : '/opus-lstsar-manager'), true, 302);
exit;
PHP;

$chainPage = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$body = '<section class="ops-panel ops-chain-explainer" data-contract="P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE">';
$body .= '<h2>Chaîne complète OPS / LSTSAR</h2>';
$body .= '<p>Lecture fonctionnelle unique : authentification, droits, état, commande, modèles, connexions, exécution, audit.</p>';
$body .= '<ol class="ops-chain-flow ops-chain-flow-large">';
foreach (p7ops_sf_profiler_chain() as $step) {
    $body .= '<li class="ops-chain-step"><a href="' . p7ops_sf_h((string) $step['route']) . '"><strong>' . p7ops_sf_h((string) $step['label']) . '</strong><span>Status: ' . p7ops_sf_h((string) $step['status']) . '</span></a></li>';
}
$body .= '</ol>';
$body .= '<div class="ops-chain-callouts">';
$body .= '<article><h3>Models</h3><p>Les modèles portent database, tables, colonnes, source model et destination model.</p><a class="ops-action-button" href="/opus-lstsar-manager/models">Open Models</a></article>';
$body .= '<article><h3>ODBC Manager</h3><p>Les DSN source/destination doivent être accessibles depuis le parcours LSTSAR.</p><a class="ops-action-button" href="/opus-lstsar-manager/odbc-manager">Open ODBC Manager</a></article>';
$body .= '<article><h3>Profiler</h3><p><code>profiler=1</code> active le profiler en session jusqu’à sortie explicite.</p><a class="ops-action-button" href="/opus-lstsar-manager/profiler">Open Profiler</a></article>';
$body .= '</div></section>';

p7ops_render_shell('OPUS OPS Chain', $body);
PHP;

p7sf_write($publicDir . '/profiler.php', $profilerPage);
p7sf_write($publicDir . '/profiler-exit.php', $profilerExit);
p7sf_write($publicDir . '/chain.php', $chainPage);

$cssFile = $publicDir . '/ops-ui.css';
$css = p7sf_read($cssFile);
if (!str_contains($css, 'P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE')) {
    $css .= PHP_EOL;
    $css .= '/* P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE */' . PHP_EOL;
    $css .= '.ops-profiler-panel{display:none!important}' . PHP_EOL;
    $css .= 'body{padding-bottom:4rem}' . PHP_EOL;
    $css .= '.sf-toolbar{position:fixed;left:0;right:0;bottom:0;z-index:5000;display:flex;flex-wrap:wrap;align-items:center;gap:0;background:#111827;color:#f9fafb;border-top:1px solid #374151;box-shadow:0 -8px 24px rgba(0,0,0,.35);font:13px/1.35 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}' . PHP_EOL;
    $css .= '.sf-toolbar-block{display:flex;align-items:center;gap:.35rem;padding:.65rem .85rem;border-right:1px solid #374151;color:#f9fafb;text-decoration:none;white-space:nowrap}' . PHP_EOL;
    $css .= '.sf-toolbar-block strong{color:#67e8f9}.sf-toolbar-main{background:#0f172a;font-weight:800}.sf-toolbar-exit{margin-left:auto;background:#3f1d1d;color:#fecaca}' . PHP_EOL;
    $css .= '.sf-profiler-page{display:grid;gap:1rem}.sf-profiler-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;border:1px solid rgba(96,165,250,.35);border-radius:1rem;padding:1rem;background:rgba(15,23,42,.75)}' . PHP_EOL;
    $css .= '.sf-profiler-tabs{display:flex;flex-wrap:wrap;gap:.5rem}.sf-profiler-tabs a{border:1px solid rgba(96,165,250,.35);border-radius:999px;padding:.55rem .8rem;text-decoration:none;color:#f8fafc;background:#020617}' . PHP_EOL;
    $css .= '.sf-profiler-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));gap:1rem}.sf-profiler-panel{min-width:0;border:1px solid rgba(96,165,250,.35);border-radius:1rem;padding:1rem;background:rgba(2,6,23,.65)}' . PHP_EOL;
    $css .= '.sf-profiler-kv{display:grid;grid-template-columns:minmax(8rem,auto) minmax(0,1fr);gap:.5rem .8rem}.sf-profiler-kv dt{font-weight:800;color:#67e8f9}.sf-profiler-kv dd{margin:0;min-width:0;overflow-wrap:anywhere}' . PHP_EOL;
    $css .= '.sf-profiler-log-list{display:grid;gap:.5rem}.sf-profiler-log-list code{overflow-wrap:anywhere}.ops-chain-flow{counter-reset:opschain;display:grid;gap:.65rem;margin:0;padding:0;list-style:none}.ops-chain-step{counter-increment:opschain;min-width:0}.ops-chain-step a{display:grid;grid-template-columns:auto minmax(0,1fr);gap:.75rem;align-items:start;border:1px solid rgba(34,211,238,.35);border-radius:.9rem;padding:.8rem;text-decoration:none;color:#f8fafc;background:rgba(2,6,23,.7)}.ops-chain-step a:before{content:counter(opschain);display:grid;place-items:center;width:2rem;height:2rem;border-radius:999px;background:#155e75;color:#67e8f9;font-weight:900}.ops-chain-step span{grid-column:2;color:#cbd5e1}.ops-chain-flow-large{grid-template-columns:repeat(auto-fit,minmax(18rem,1fr))}.ops-chain-callouts{display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:1rem;margin-top:1rem}.ops-chain-callouts article{border:1px solid rgba(96,165,250,.35);border-radius:1rem;padding:1rem;background:rgba(2,6,23,.65)}' . PHP_EOL;
    $css .= '.ops-table-wrap{overflow-x:auto}.ops-table{table-layout:auto}.ops-table code,.ops-table td code,.ops-card code{white-space:nowrap;overflow-wrap:normal;word-break:normal}.ops-kv-grid,.ops-summary-grid{grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))}' . PHP_EOL;
    $css .= '@media (max-width:900px){body{padding-bottom:7rem}.sf-toolbar{font-size:12px}.sf-toolbar-exit{margin-left:0}.sf-profiler-grid{grid-template-columns:1fr}.sf-profiler-kv{grid-template-columns:1fr}.ops-chain-flow-large{grid-template-columns:1fr}}' . PHP_EOL;
}

p7sf_write($cssFile, $css);

$readmeFile = $siteDir . '/README.md';
$readme = is_file($readmeFile) ? p7sf_read($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;
if (!str_contains($readme, 'P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Replaces the mini profiler display with a Symfony-style toolbar and typed profiler page.' . PHP_EOL;
    $readme .= '- `profiler=1` is stored in session as `p7ops_sf_profiler_enabled` and remains active until `/opus-lstsar-manager/profiler/exit` or `profiler=0`.' . PHP_EOL;
    $readme .= '- Adds profiler panels: Request, Performance, Session, Auth/SSO, OPS Chain, Logs and Config.' . PHP_EOL;
    $readme .= '- Clarifies the full chain: Auth/SSO, RBAC, FSM, CL, Models, Database/tables, ODBC Manager, LSTSAR, Actions, Logs/Profiler.' . PHP_EOL;
    $readme .= '- Improves technical identifier rendering by avoiding ugly forced breaks in DSN/model/table values.' . PHP_EOL;
}
p7sf_write($readmeFile, $readme);

echo 'P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE_UPDATED' . PHP_EOL;
