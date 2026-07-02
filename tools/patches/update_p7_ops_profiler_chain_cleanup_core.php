<?php
declare(strict_types=1);

$root = getcwd();
$site = $root . '/sites/opus-p7-ops';
$public = $site . '/public';
$config = $site . '/config';
$logDir = $root . '/var/logs/opus_lstsar-manager';

function rw(string $file): string {
    $s = file_get_contents($file);
    if ($s === false) { fwrite(STDERR, "READ_FAILED:$file\n"); exit(1); }
    return $s;
}
function ww(string $file, string $s): void {
    if (file_put_contents($file, $s) === false) { fwrite(STDERR, "WRITE_FAILED:$file\n"); exit(1); }
}

foreach ([$site, $public, $config] as $d) {
    if (!is_dir($d)) { fwrite(STDERR, "DIR_MISSING:$d\n"); exit(1); }
}
if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    fwrite(STDERR, "LOG_DIR_CREATE_FAILED:$logDir\n"); exit(1);
}
file_put_contents($root . '/var/logs/.gitignore', "*.log\n**/*.log\n!**/.gitkeep\n");
file_put_contents($logDir . '/.gitkeep', '');

$prod = $config . '/environment.prod.example.php';
if (is_file($prod)) {
    $s = rw($prod);
    if (!str_contains($s, 'environment.prod.example.php')) {
        $s = str_replace("declare(strict_types=1);\n", "declare(strict_types=1);\n\n// environment.prod.example.php\n", $s);
        ww($prod, $s);
    }
}

$langFile = $public . '/language.php';
$lang = rw($langFile);
$lang = str_replace("\$GLOBALS['p7ops_profiler_start_ns'] = hrtime(true);", "\$GLOBALS['p7ops_profiler_start_microtime'] = microtime(true);", $lang);
$lang = str_replace('p7ops_profiler_start_ns', 'p7ops_profiler_start_microtime', $lang);
$lang = str_replace('$start = (int) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));', '$start = (float) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));', $lang);
$lang = str_replace('$start = (int) ($GLOBALS[\'p7ops_profiler_start_ns\'] ?? hrtime(true));', '$start = (float) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));', $lang);
$lang = str_replace('round((hrtime(true) - $start) / 1000000, 3)', 'round((microtime(true) - $start) * 1000, 3)', $lang);
$lang = str_replace('round((microtime(true) - $start) / 1000000, 3)', 'round((microtime(true) - $start) * 1000, 3)', $lang);

$runtime = <<<'PHP'

/** P7_OPS_PROFILER_CHAIN_CLEANUP_CORE */
if (!function_exists('p7ops_clean_h')) {
    function p7ops_clean_h(string $v): string {
        return function_exists('p7ops_h') ? p7ops_h($v) : htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('p7ops_clean_session_start_once')) {
    function p7ops_clean_session_start_once(): void {
        if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
            session_name('OPUSLSTSAROPS');
            session_start();
        }
    }
}
if (!function_exists('p7ops_clean_is_static_path')) {
    function p7ops_clean_is_static_path(string $p): bool {
        return (bool) preg_match('~\.(?:ico|png|jpe?g|gif|svg|webp|css|js|map|woff2?|ttf|eot)$~i', $p);
    }
}
if (!function_exists('p7ops_clean_profiler_handle_request')) {
    function p7ops_clean_profiler_handle_request(): void {
        if (PHP_SAPI === 'cli') { return; }
        p7ops_clean_session_start_once();
        $v = strtolower((string) ($_GET['profiler'] ?? ''));
        if (in_array($v, ['1','true','yes','on'], true)) { $_SESSION['p7ops_clean_profiler_enabled'] = true; }
        if (in_array($v, ['0','false','no','off','exit'], true)) { unset($_SESSION['p7ops_clean_profiler_enabled']); }
    }
}
if (!function_exists('p7ops_clean_profiler_enabled')) {
    function p7ops_clean_profiler_enabled(): bool {
        if (PHP_SAPI === 'cli') { return (string)($_GET['profiler'] ?? '') === '1'; }
        p7ops_clean_profiler_handle_request();
        return (bool) ($_SESSION['p7ops_clean_profiler_enabled'] ?? false);
    }
}
if (!function_exists('p7ops_clean_profiler_disable')) {
    function p7ops_clean_profiler_disable(): void {
        if (PHP_SAPI === 'cli') { return; }
        p7ops_clean_session_start_once();
        unset($_SESSION['p7ops_clean_profiler_enabled'], $_SESSION['p7ops_clean_profiler_history']);
    }
}
if (!function_exists('p7ops_clean_chain_steps')) {
    function p7ops_clean_chain_steps(): array {
        return [
            ['n'=>'01','key'=>'auth','title'=>'Auth / SSO','text'=>'Connexion contrôlée, logout, SSO optionnel, session.','route'=>'/opus-lstsar-manager/login'],
            ['n'=>'02','key'=>'rbac','title'=>'RBAC / Policies','text'=>'Droits utilisateur, rôles, accès aux actions OPS.','route'=>'/opus-lstsar-manager/profiler#auth'],
            ['n'=>'03','key'=>'fsm','title'=>'FSM','text'=>'État de l’opération avant toute commande.','route'=>'/opus-lstsar-manager/fsm'],
            ['n'=>'04','key'=>'cl','title'=>'CL','text'=>'Couche commande / orchestration entre FSM et moteur.','route'=>'/opus-lstsar-manager/cl'],
            ['n'=>'05','key'=>'models','title'=>'Models','text'=>'Modèles source/destination, structure métier et technique.','route'=>'/opus-lstsar-manager/models'],
            ['n'=>'06','key'=>'database','title'=>'Database / Tables','text'=>'Base, tables, colonnes, contraintes et types attendus.','route'=>'/opus-lstsar-manager/models#database'],
            ['n'=>'07','key'=>'odbc','title'=>'ODBC Manager','text'=>'Drivers, DSN, tests de connexion source et destination.','route'=>'/opus-lstsar-manager/odbc-manager'],
            ['n'=>'08','key'=>'lstsar','title'=>'LSTSAR','text'=>'Load, Secure, Transform, Store, Audit.','route'=>'/opus-lstsar-manager/operations'],
            ['n'=>'09','key'=>'actions','title'=>'Actions','text'=>'Preview, dry-run, audit, exécution contrôlée.','route'=>'/opus-lstsar-manager/command-center'],
            ['n'=>'10','key'=>'observability','title'=>'Logs / Profiler','text'=>'Access log, auth log, profiler, diagnostics.','route'=>'/opus-lstsar-manager/profiler'],
        ];
    }
}
if (!function_exists('p7ops_clean_profiler_token')) {
    function p7ops_clean_profiler_token(): string {
        if (!isset($GLOBALS['p7ops_clean_profiler_token'])) {
            $GLOBALS['p7ops_clean_profiler_token'] = substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 10);
        }
        return (string) $GLOBALS['p7ops_clean_profiler_token'];
    }
}
if (!function_exists('p7ops_clean_profiler_collect')) {
    function p7ops_clean_profiler_collect(string $phase = 'collect'): array {
        $start = (float) ($GLOBALS['p7ops_clean_profiler_start'] ?? microtime(true));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: ''));
        $user = function_exists('p7ops_current_user') ? p7ops_current_user() : null;
        return [
            'token'=>p7ops_clean_profiler_token(), 'phase'=>$phase,
            'environment'=>function_exists('p7ops_environment') ? p7ops_environment() : 'dev',
            'status'=>http_response_code() ?: 200,
            'duration_ms'=>round((microtime(true) - $start) * 1000, 3),
            'memory_peak_bytes'=>memory_get_peak_usage(true), 'memory_current_bytes'=>memory_get_usage(true),
            'method'=>(string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), 'uri'=>$uri, 'path'=>$path,
            'query'=>(string)(parse_url($uri, PHP_URL_QUERY) ?: ''),
            'remote_addr'=>(string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent'=>(string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'user'=>is_array($user) ? (string)($user['username'] ?? 'signed-in') : 'anonymous',
            'roles'=>is_array($user) ? implode(', ', array_map('strval', (array)($user['roles'] ?? []))) : '',
            'session_profiler'=>p7ops_clean_profiler_enabled() ? 'enabled' : 'disabled',
        ];
    }
}
if (!function_exists('p7ops_clean_profiler_store')) {
    function p7ops_clean_profiler_store(array $p): void {
        if (p7ops_clean_is_static_path((string)($p['path'] ?? ''))) { return; }
        if (PHP_SAPI !== 'cli') {
            p7ops_clean_session_start_once();
            $h = array_values((array)($_SESSION['p7ops_clean_profiler_history'] ?? []));
            $h[] = $p;
            $_SESSION['p7ops_clean_profiler_history'] = array_values(array_slice($h, -20));
        }
        if (function_exists('p7ops_log_line')) {
            p7ops_log_line('profiler.log', ['level'=>'INFO','event'=>'typed_profile','token'=>(string)($p['token'] ?? ''),'method'=>(string)($p['method'] ?? ''),'uri'=>(string)($p['uri'] ?? ''),'status'=>(int)($p['status'] ?? 200),'duration_ms'=>(float)($p['duration_ms'] ?? 0),'memory_peak_bytes'=>(int)($p['memory_peak_bytes'] ?? 0)]);
        }
    }
}
if (!function_exists('p7ops_clean_profiler_history')) {
    function p7ops_clean_profiler_history(): array {
        if (PHP_SAPI === 'cli') { return []; }
        p7ops_clean_session_start_once();
        return array_values((array)($_SESSION['p7ops_clean_profiler_history'] ?? []));
    }
}
if (!function_exists('p7ops_clean_toolbar_html')) {
    function p7ops_clean_toolbar_html(array $p): string {
        $cur = (string)($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager');
        $exit = '/opus-lstsar-manager/profiler/exit?next=' . rawurlencode($cur);
        return '<div class="opus-profiler-toolbar" data-contract="P7_OPS_PROFILER_CHAIN_CLEANUP_CORE">'
            . '<a href="/opus-lstsar-manager/profiler" class="optb-brand">OPUS Profiler <strong>#' . p7ops_clean_h((string)($p['token'] ?? '')) . '</strong></a>'
            . '<span>HTTP <strong>' . p7ops_clean_h((string)($p['status'] ?? '')) . '</strong></span>'
            . '<span>Time <strong>' . p7ops_clean_h((string)($p['duration_ms'] ?? '')) . ' ms</strong></span>'
            . '<span>Mem <strong>' . p7ops_clean_h((string)($p['memory_peak_bytes'] ?? '')) . '</strong></span>'
            . '<a href="/opus-lstsar-manager/chain">Chain</a><a href="/opus-lstsar-manager/profiler">Details</a>'
            . '<a href="' . p7ops_clean_h($exit) . '" class="optb-exit">Exit</a></div>';
    }
}
if (!function_exists('p7ops_clean_profiler_boot_once')) {
    function p7ops_clean_profiler_boot_once(): void {
        static $booted = false;
        if ($booted || PHP_SAPI === 'cli') { return; }
        $booted = true;
        p7ops_clean_profiler_handle_request();
        if (!p7ops_clean_profiler_enabled()) { return; }
        $path = rawurldecode((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''));
        if (p7ops_clean_is_static_path($path)) { return; }
        $GLOBALS['p7ops_clean_profiler_start'] = microtime(true);
        p7ops_clean_profiler_token();
        ob_start(static function (string $html): string {
            $p = p7ops_clean_profiler_collect('toolbar');
            p7ops_clean_profiler_store($p);
            $toolbar = p7ops_clean_toolbar_html($p);
            if (stripos($html, '</body>') !== false) {
                return preg_replace('/<\/body>/i', $toolbar . '</body>', $html, 1) ?: ($html . $toolbar);
            }
            return $html . $toolbar;
        });
    }
}
PHP;

if (!str_contains($lang, 'P7_OPS_PROFILER_CHAIN_CLEANUP_CORE')) {
    $lang .= PHP_EOL . $runtime . PHP_EOL;
}
ww($langFile, $lang);

$router = <<<'PHP'
<?php
/** P7_OPS_PROFILER_CHAIN_CLEANUP_CORE */
declare(strict_types=1);

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$decodedPath = rawurldecode($rawPath);
$path = $decodedPath === '/' ? '/' : rtrim($decodedPath, '/');

$staticFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if ($path !== '/' && is_file($staticFile)) {
    return false;
}
if ($path !== '/' && preg_match('~\.(?:ico|png|jpe?g|gif|svg|webp|css|js|map|woff2?|ttf|eot)$~i', $path)) {
    http_response_code(404);
    return true;
}

require_once __DIR__ . '/language.php';

p7ops_access_log_once();
p7ops_clean_profiler_boot_once();

$nativeRoute = p7ops_resolve_native_route($path);
if ($nativeRoute !== null) {
    $_GET['lang'] = (string) $nativeRoute['lang'];
    $_GET['site'] = $_GET['site'] ?? 'site-alpha';
    $path = (string) $nativeRoute['canonical'];
}

$publicRoutes = [
    '/opus-lstsar-manager/login' => 'login.php',
    '/login' => 'login.php',
    '/opus-lstsar-manager/signin' => 'login.php',
    '/opus-lstsar-manager/sign-in' => 'login.php',
    '/opus-lstsar-manager/logout' => 'logout.php',
    '/logout' => 'logout.php',
    '/opus-lstsar-manager/profiler' => 'profiler.php',
    '/opus-lstsar-manager/profiler/exit' => 'profiler-exit.php',
    '/_profiler' => 'profiler.php',
    '/_profiler/exit' => 'profiler-exit.php',
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
ww($public . '/router.php', $router);

$profilerPage = <<<'PHP'
<?php
declare(strict_types=1);
require_once __DIR__ . '/language.php';
p7ops_clean_session_start_once();
$history = p7ops_clean_profiler_history();
$current = $history !== [] ? $history[array_key_last($history)] : p7ops_clean_profiler_collect('page');
$e = static fn(string $v): string => p7ops_clean_h($v);
$kv = static function(array $rows) use ($e): string {
    $o = '<dl class="opf-kv">';
    foreach ($rows as $k=>$v) { $o .= '<dt>'.$e((string)$k).'</dt><dd>'.$e((string)$v).'</dd>'; }
    return $o.'</dl>';
};
$chain = '<ol class="opf-chain">';
foreach (p7ops_clean_chain_steps() as $s) {
    $chain .= '<li><a href="'.$e((string)$s['route']).'"><span>'.$e((string)$s['n']).'</span><strong>'.$e((string)$s['title']).'</strong><em>'.$e((string)$s['text']).'</em></a></li>';
}
$chain .= '</ol>';
$hist = '<table><thead><tr><th>Token</th><th>Method</th><th>URI</th><th>Status</th><th>Time</th></tr></thead><tbody>';
foreach (array_reverse($history) as $r) {
    $hist .= '<tr><td><code>'.$e((string)($r['token']??'')).'</code></td><td>'.$e((string)($r['method']??'')).'</td><td><code>'.$e((string)($r['uri']??'')).'</code></td><td>'.$e((string)($r['status']??'')).'</td><td>'.$e((string)($r['duration_ms']??'')).' ms</td></tr>';
}
$hist .= '</tbody></table>';
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OPUS Web Profiler</title><link rel="stylesheet" href="/ops-ui.css"><style>
body{margin:0;background:#0b1220;color:#f8fafc;font:15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.opf{width:min(1280px,calc(100vw - 2rem));margin:0 auto;padding:1rem 1rem 5rem}.hero,.panel{border:1px solid rgba(96,165,250,.35);border-radius:18px;background:#111827;padding:1rem;margin:1rem 0}.hero{display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap}.hero h1{font-size:clamp(2rem,4vw,4rem);margin:.2rem 0}.actions,.tabs{display:flex;flex-wrap:wrap;gap:.5rem}.actions a,.tabs a{border:1px solid rgba(34,211,238,.45);border-radius:999px;padding:.55rem .85rem;color:#f8fafc;text-decoration:none;background:#020617;font-weight:800}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));gap:1rem}.opf-kv{display:grid;grid-template-columns:minmax(8rem,auto) minmax(0,1fr);gap:.55rem .85rem}.opf-kv dt{color:#67e8f9;font-weight:900}.opf-kv dd{margin:0;overflow-wrap:anywhere}.opf-chain{display:grid;grid-template-columns:repeat(auto-fit,minmax(19rem,1fr));gap:.75rem;padding:0;list-style:none}.opf-chain a{display:grid;grid-template-columns:auto minmax(0,1fr);gap:.5rem .75rem;border:1px solid rgba(34,211,238,.35);border-radius:14px;padding:.8rem;text-decoration:none;color:#f8fafc;background:#020617}.opf-chain span{grid-row:1/3;display:grid;place-items:center;width:2.4rem;height:2.4rem;border-radius:999px;background:#155e75;color:#67e8f9;font-weight:950}.opf-chain em{font-style:normal;color:#cbd5e1}table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid rgba(96,165,250,.25);padding:.65rem;text-align:left;vertical-align:top}code{white-space:nowrap}.scroll{overflow:auto}@media(max-width:800px){.grid{grid-template-columns:1fr}.opf-kv{grid-template-columns:1fr}}
</style></head><body><main class="opf" data-contract="P7_OPS_PROFILER_CHAIN_CLEANUP_CORE"><section class="hero"><div><p><strong>OPUS OPS</strong> · profiler typé session</p><h1>OPUS Web Profiler</h1><p><code>profiler=1</code> active le mode profiler en SESSION jusqu’à sortie explicite.</p></div><nav class="actions"><a href="/opus-lstsar-manager?site=site-alpha&lang=fr&profiler=1">Enable</a><a href="/opus-lstsar-manager/profiler/exit">Exit profiler</a><a href="/opus-lstsar-manager/chain">OPS Chain</a><a href="/opus-lstsar-manager/operations">Operations</a></nav></section><nav class="tabs"><a href="#request">Request</a><a href="#performance">Performance</a><a href="#session">Session</a><a href="#auth">Auth / SSO</a><a href="#chain">Chain</a><a href="#logs">Logs</a><a href="#history">History</a></nav><section class="grid"><article id="request" class="panel"><h2>Request</h2><?= $kv(['Token'=>$current['token']??'','Method'=>$current['method']??'','URI'=>$current['uri']??'','Path'=>$current['path']??'','Query'=>$current['query']??'','Remote address'=>$current['remote_addr']??'']) ?></article><article id="performance" class="panel"><h2>Performance</h2><?= $kv(['Status'=>$current['status']??'','Duration'=>(string)($current['duration_ms']??'').' ms','Peak memory'=>(string)($current['memory_peak_bytes']??'').' bytes','Current memory'=>(string)($current['memory_current_bytes']??'').' bytes']) ?></article><article id="session" class="panel"><h2>Session</h2><?= $kv(['Profiler mode'=>$current['session_profiler']??'','Persistence key'=>'$_SESSION[p7ops_clean_profiler_enabled]','Exit route'=>'/opus-lstsar-manager/profiler/exit']) ?></article><article id="auth" class="panel"><h2>Auth / SSO</h2><?= $kv(['User'=>$current['user']??'','Roles'=>$current['roles']??'','Environment'=>$current['environment']??'','SSO'=>((p7ops_config()['sso']['enabled']??false)?'enabled':'disabled')]) ?></article></section><section id="chain" class="panel"><h2>Chaîne fonctionnelle complète</h2><?= $chain ?></section><section id="logs" class="panel"><h2>Logs</h2><?= $kv(['Access'=>'var/logs/opus_lstsar-manager/access.log','Auth'=>'var/logs/opus_lstsar-manager/auth.log','Profiler'=>'var/logs/opus_lstsar-manager/profiler.log','PHP server'=>'var/logs/opus_lstsar-manager/php-server.log']) ?></section><section id="history" class="panel"><h2>History</h2><div class="scroll"><?= $hist ?></div></section></main></body></html>
PHP;

$profilerExit = <<<'PHP'
<?php
declare(strict_types=1);
require_once __DIR__ . '/language.php';
p7ops_clean_profiler_disable();
$next = (string) ($_GET['next'] ?? '/opus-lstsar-manager');
header('Location: ' . ($next !== '' ? $next : '/opus-lstsar-manager'), true, 302);
exit;
PHP;

$chainPage = <<<'PHP'
<?php
declare(strict_types=1);
require_once __DIR__ . '/language.php';
$e = static fn(string $v): string => p7ops_clean_h($v);
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OPUS OPS Chain</title><link rel="stylesheet" href="/ops-ui.css"><style>
body{margin:0;background:#0b1220;color:#f8fafc;font:15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.page{width:min(1280px,calc(100vw - 2rem));margin:0 auto;padding:1rem 1rem 5rem}.hero,.panel{border:1px solid rgba(96,165,250,.35);border-radius:18px;background:#111827;padding:1rem;margin:1rem 0}.hero h1{font-size:clamp(2rem,4vw,4rem);margin:.2rem 0}.flow{display:grid;grid-template-columns:repeat(auto-fit,minmax(20rem,1fr));gap:.85rem;padding:0;margin:0;list-style:none}.flow a{display:grid;grid-template-columns:auto minmax(0,1fr);gap:.55rem .8rem;min-height:7rem;border:1px solid rgba(34,211,238,.35);border-radius:16px;padding:1rem;text-decoration:none;color:#f8fafc;background:#020617}.n{grid-row:1/3;display:grid;place-items:center;width:2.7rem;height:2.7rem;border-radius:999px;background:#155e75;color:#67e8f9;font-weight:950}.flow strong{font-size:1.1rem}.flow span:last-child{color:#cbd5e1}.actions{display:flex;flex-wrap:wrap;gap:.5rem}.actions a{border:1px solid rgba(34,211,238,.45);border-radius:999px;padding:.55rem .85rem;color:#f8fafc;text-decoration:none;background:#020617;font-weight:800}
</style></head><body><main class="page" data-contract="P7_OPS_PROFILER_CHAIN_CLEANUP_CORE"><section class="hero"><p><strong>OPUS OPS</strong> · parcours lisible</p><h1>Chaîne complète LSTSAR</h1><p>Ordre fonctionnel unique : authentifier, autoriser, contrôler l’état, commander, résoudre les modèles, vérifier les connexions, exécuter LSTSAR, auditer.</p><nav class="actions"><a href="/opus-lstsar-manager/operations">Operations</a><a href="/opus-lstsar-manager/models">Models</a><a href="/opus-lstsar-manager/odbc-manager">ODBC Manager</a><a href="/opus-lstsar-manager/profiler">Profiler</a></nav></section><section class="panel"><ol class="flow"><?php foreach (p7ops_clean_chain_steps() as $s): ?><li><a href="<?= $e((string)$s['route']) ?>"><span class="n"><?= $e((string)$s['n']) ?></span><strong><?= $e((string)$s['title']) ?></strong><span><?= $e((string)$s['text']) ?></span></a></li><?php endforeach; ?></ol></section></main></body></html>
PHP;

ww($public . '/profiler.php', $profilerPage);
ww($public . '/profiler-exit.php', $profilerExit);
ww($public . '/chain.php', $chainPage);

$cssFile = $public . '/ops-ui.css';
$css = rw($cssFile);
if (!str_contains($css, 'P7_OPS_PROFILER_CHAIN_CLEANUP_CORE')) {
    $css .= "\n/* P7_OPS_PROFILER_CHAIN_CLEANUP_CORE */\n";
    $css .= ".ops-profiler-panel,.p7ops-sf-toolbar,.sf-toolbar{display:none!important}\n";
    $css .= "body{padding-bottom:3.25rem}.opus-profiler-toolbar{position:fixed;left:0;right:0;bottom:0;z-index:9000;display:flex;align-items:center;gap:0;background:#111827;color:#f8fafc;border-top:1px solid #374151;box-shadow:0 -8px 28px rgba(0,0,0,.35);font:12px/1.35 system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}.opus-profiler-toolbar a,.opus-profiler-toolbar span{display:flex;align-items:center;gap:.35rem;padding:.6rem .75rem;border-right:1px solid #374151;color:#f8fafc;text-decoration:none;white-space:nowrap}.opus-profiler-toolbar strong{color:#67e8f9}.opus-profiler-toolbar .optb-brand{font-weight:900;background:#020617}.opus-profiler-toolbar .optb-exit{margin-left:auto;background:#7f1d1d;color:#fecaca}\n";
    $css .= ".ops-site-header,.ops-header,.ops-topbar,.ops-toolbar,.ops-nav,.ops-navigation,.ops-panel:first-child{display:flex!important;flex-wrap:wrap!important;align-items:center;gap:1rem;min-width:0;max-width:100%;overflow:visible}.ops-language-selector,[class*='language-selector'],[class*='LanguageSelector']{position:static!important;inset:auto!important;transform:none!important;margin-left:auto;max-width:100%;flex:0 1 auto}.ops-language-selector select,[class*='language-selector'] select{max-width:min(20rem,100%)}\n";
    $css .= ".ops-table-wrap{overflow-x:auto}.ops-table{table-layout:auto;min-width:920px}.ops-table code,.ops-table td code,.ops-card code{white-space:nowrap!important;overflow-wrap:normal!important;word-break:normal!important}.ops-kv-grid,.ops-summary-grid{grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))!important}.ops-kv,.ops-card{min-width:0}\n";
    $css .= "@media(max-width:900px){body{padding-bottom:6rem}.opus-profiler-toolbar{flex-wrap:wrap}.opus-profiler-toolbar .optb-exit{margin-left:0}.ops-language-selector,[class*='language-selector']{order:20;margin-left:0;width:100%;flex:1 1 100%}.ops-language-selector select,[class*='language-selector'] select{width:100%;max-width:100%}}\n";
}
ww($cssFile, $css);

$readmeFile = $site . '/README.md';
$readme = is_file($readmeFile) ? rw($readmeFile) : "# OPUS P7 OPS\n";
if (!str_contains($readme, 'P7_OPS_PROFILER_CHAIN_CLEANUP_CORE')) {
    $readme .= "\n## P7_OPS_PROFILER_CHAIN_CLEANUP_CORE\n\n";
    $readme .= "- Replaces the confusing profiler attempts with one clean typed profiler implementation.\n";
    $readme .= "- `profiler=1` is stored in session as `p7ops_clean_profiler_enabled` until `/opus-lstsar-manager/profiler/exit` or `profiler=0`.\n";
    $readme .= "- Static assets such as `/favicon.ico` and `/ops-ui.css` are never captured as profiler pages.\n";
    $readme .= "- Clarifies the OPS chain: Auth/SSO, RBAC, FSM, CL, Models, Database/Tables, ODBC Manager, LSTSAR, Actions, Logs/Profiler.\n";
}
ww($readmeFile, $readme);

echo "P7_OPS_PROFILER_CHAIN_CLEANUP_CORE_UPDATED\n";
