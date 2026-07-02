<?php
declare(strict_types=1);

echo 'P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';
$configDir = $root . '/sites/opus-p7-ops/config';
$logDir = $root . '/var/logs/opus_lstsar-manager';

$files = [
    $publicDir . '/language.php',
    $publicDir . '/router.php',
    $publicDir . '/profiler.php',
    $publicDir . '/profiler-exit.php',
    $publicDir . '/chain.php',
    $publicDir . '/ops-ui.css',
    $configDir . '/environment.prod.example.php',
    $root . '/sites/opus-p7-ops/README.md',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('SF_PROFILER_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('SF_PROFILER_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE',
    'p7ops_sf_profiler_boot_once',
    'p7ops_sf_profiler_enabled',
    'p7ops_sf_profiler_toolbar_html',
    'p7ops_sf_profiler_history',
    'p7ops_sf_profiler_disable',
    'p7ops_sf_profiler_enabled',
    'p7ops_sf_profiler_chain',
    '/opus-lstsar-manager/profiler',
    '/opus-lstsar-manager/profiler/exit',
    'p7ops_sf_profiler_enabled',
    'p7ops_sf_profiler_history',
    'OPS Chain',
    'FSM state control',
    'CL command layer',
    'Models registry',
    'ODBC Manager / DSN / connection tests',
    'LSTSAR Load / Secure / Transform / Store',
    'position:fixed',
    'sf-toolbar',
    'sf-profiler-panel',
    'environment.prod.example.php',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('SF_PROFILER_MARKER_MISSING: ' . $marker);
    }
}

if (str_contains(file_get_contents($publicDir . '/language.php') ?: '', 'hrtime(true)')) {
    throw new RuntimeException('SF_PROFILER_HRTIME_STILL_PRESENT');
}

echo 'CHECK_P7_OPS_SF_PROFILER_MARKERS=OK' . PHP_EOL;

require_once $publicDir . '/language.php';

$_GET = ['profiler' => '1', 'site' => 'site-alpha', 'lang' => 'fr'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager?site=site-alpha&lang=fr&profiler=1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE_SMOKE';

$profile = p7ops_sf_profiler_collect('smoke');
p7ops_sf_profiler_store($profile);
$toolbar = p7ops_sf_profiler_toolbar_html($profile);

foreach ([
    'OPUS Profiler',
    'Exit profiler',
    'OPS Chain',
    'Models',
    'ODBC',
    'P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE',
] as $marker) {
    if (!str_contains($toolbar, $marker)) {
        throw new RuntimeException('SF_PROFILER_TOOLBAR_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_SF_PROFILER_TOOLBAR=OK' . PHP_EOL;

$chain = p7ops_sf_profiler_chain();
$keys = array_map(static fn(array $item): string => (string) $item['key'], $chain);
foreach (['auth', 'session', 'fsm', 'cl', 'models', 'database', 'odbc', 'lstsar', 'actions', 'observability'] as $key) {
    if (!in_array($key, $keys, true)) {
        throw new RuntimeException('SF_PROFILER_CHAIN_KEY_MISSING: ' . $key);
    }
}

echo 'CHECK_P7_OPS_SF_PROFILER_CHAIN=OK' . PHP_EOL;

$render = static function (string $file, string $uri): string {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = ['site' => 'site-alpha', 'lang' => 'fr', 'profiler' => '1'];

    ob_start();
    (static function (string $__file): void {
        require $__file;
    })($file);
    $html = ob_get_clean();

    return is_string($html) ? $html : '';
};

$profilerHtml = $render($publicDir . '/profiler.php', '/opus-lstsar-manager/profiler?site=site-alpha&lang=fr');
foreach ([
    'OPUS Web Profiler',
    'Request',
    'Performance',
    'Session',
    'Auth / SSO',
    'FSM + CL + Models + ODBC + LSTSAR',
    'Logs',
    'Config',
] as $marker) {
    if (!str_contains($profilerHtml, $marker)) {
        throw new RuntimeException('SF_PROFILER_PAGE_MARKER_MISSING: ' . $marker);
    }
}

$chainHtml = $render($publicDir . '/chain.php', '/opus-lstsar-manager/chain?site=site-alpha&lang=fr');
foreach ([
    'Chaîne complète OPS / LSTSAR',
    'FSM state control',
    'CL command layer',
    'Models registry',
    'ODBC Manager / DSN / connection tests',
    'LSTSAR Load / Secure / Transform / Store',
] as $marker) {
    if (!str_contains($chainHtml, $marker)) {
        throw new RuntimeException('SF_PROFILER_CHAIN_PAGE_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_SF_PROFILER_PAGES=OK' . PHP_EOL;

$profilerLog = $logDir . '/profiler.log';
if (!is_file($profilerLog)) {
    throw new RuntimeException('SF_PROFILER_LOG_MISSING');
}

$tail = file_get_contents($profilerLog, false, null, max(0, (int) filesize($profilerLog) - 4096));
if (!is_string($tail) || !str_contains($tail, '"event":"symfony_style_profile"')) {
    throw new RuntimeException('SF_PROFILER_LOG_EVENT_MISSING');
}

echo 'CHECK_P7_OPS_SF_PROFILER_LOG=OK' . PHP_EOL;
echo 'P7_OPS_SYMFONY_STYLE_PROFILER_SESSION_CORE_SMOKE_OK' . PHP_EOL;
