<?php
declare(strict_types=1);

echo 'P7_OPS_CHAIN_AUTH_ENV_CORE_SMOKE' . PHP_EOL;
echo 'P7_OPS_CHAIN_AUTH_ENV_FIX_CORE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$siteDir = $root . '/sites/opus-p7-ops';
$publicDir = $siteDir . '/public';
$configDir = $siteDir . '/config';
$logDir = $root . '/var/logs/opus_lstsar-manager';

$files = [
    $publicDir . '/language.php',
    $publicDir . '/router.php',
    $publicDir . '/login.php',
    $publicDir . '/logout.php',
    $publicDir . '/chain.php',
    $publicDir . '/models.php',
    $publicDir . '/odbc-manager.php',
    $publicDir . '/fsm.php',
    $publicDir . '/cl.php',
    $publicDir . '/sso.php',
    $configDir . '/environment.php',
    $configDir . '/environment.dev.php',
    $configDir . '/environment.prod.example.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('CHAIN_AUTH_ENV_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('CHAIN_AUTH_ENV_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'P7_OPS_CHAIN_AUTH_ENV_CORE',
    'p7ops_config',
    'p7ops_environment',
    'p7ops_sign_in',
    'p7ops_sign_out',
    'p7ops_require_signin',
    'p7ops_dependency_chain',
    'p7ops_access_log_once',
    'p7ops_profiler_panel_html',
    '/opus-lstsar-manager/login',
    '/opus-lstsar-manager/logout',
    '/opus-lstsar-manager/odbc-manager',
    '/opus-lstsar-manager/models',
    '/opus-lstsar-manager/fsm',
    '/opus-lstsar-manager/cl',
    '/opus-lstsar-manager/sso',
    'environment.dev.php',
    'environment.prod.example.php',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('CHAIN_AUTH_ENV_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_CHAIN_AUTH_ENV_MARKERS=OK' . PHP_EOL;

require_once $publicDir . '/language.php';

$config = p7ops_config();
if (($config['environment'] ?? '') !== 'dev') {
    throw new RuntimeException('CHAIN_AUTH_ENV_DEFAULT_NOT_DEV');
}

$chain = p7ops_dependency_chain();
$ids = array_map(static fn(array $item): string => (string) $item['id'], $chain);
foreach (['auth', 'sso', 'rbac', 'fsm', 'cl', 'models', 'database', 'odbc', 'lstsar', 'logs'] as $id) {
    if (!in_array($id, $ids, true)) {
        throw new RuntimeException('CHAIN_AUTH_ENV_CHAIN_ID_MISSING: ' . $id);
    }
}

echo 'CHECK_P7_OPS_DEPENDENCY_CHAIN=OK' . PHP_EOL;

$logFile = $logDir . '/access.log';
$profilerFile = $logDir . '/profiler.log';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/chain?site=site-alpha&lang=en&profiler=1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'P7_OPS_CHAIN_AUTH_ENV_CORE_SMOKE';
$_GET = ['site' => 'site-alpha', 'lang' => 'en', 'profiler' => '1'];

$beforeLog = is_file($logFile) ? (int) filesize($logFile) : 0;
$beforeProfiler = is_file($profilerFile) ? (int) filesize($profilerFile) : 0;

p7ops_access_log_once();
p7ops_profiler_start_once();
usleep(1000);
p7ops_profiler_finish_once('smoke');

if (!is_file($logFile) || (int) filesize($logFile) <= $beforeLog) {
    throw new RuntimeException('CHAIN_AUTH_ENV_ACCESS_LOG_NOT_APPENDED');
}

if (!is_file($profilerFile) || (int) filesize($profilerFile) <= $beforeProfiler) {
    throw new RuntimeException('CHAIN_AUTH_ENV_PROFILER_LOG_NOT_APPENDED');
}

$tail = file_get_contents($logFile, false, null, max(0, (int) filesize($logFile) - 4096));
if (!is_string($tail) || !str_contains($tail, '"uri":"/opus-lstsar-manager/chain?site=site-alpha&lang=en&profiler=1"')) {
    throw new RuntimeException('CHAIN_AUTH_ENV_ACCESS_LOG_URI_MISSING');
}

echo 'CHECK_P7_OPS_LOGS_AND_PROFILER=OK' . PHP_EOL;

$render = static function (string $file, string $uri): string {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = ['site' => 'site-alpha', 'lang' => 'en', 'profiler' => '1'];

    ob_start();
    (static function (string $__file): void {
        require $__file;
    })($file);
    $html = ob_get_clean();

    return is_string($html) ? $html : '';
};

foreach ([
    'chain' => [$publicDir . '/chain.php', '/opus-lstsar-manager/chain?site=site-alpha&lang=en&profiler=1', ['Complete OPS dependency chain', 'ODBC Manager / DSN', 'LSTSAR operations', 'Logs / profiler / audit']],
    'models' => [$publicDir . '/models.php', '/opus-lstsar-manager/models?site=site-alpha&lang=en&profiler=1', ['Models Registry', 'Database', 'Tables', 'Open ODBC Manager']],
    'odbc' => [$publicDir . '/odbc-manager.php', '/opus-lstsar-manager/odbc-manager?site=site-alpha&lang=en&profiler=1', ['ODBC Manager', 'Source DSN', 'Destination DSN', 'Connection tests']],
    'sso' => [$publicDir . '/sso.php', '/opus-lstsar-manager/sso?site=site-alpha&lang=en&profiler=1', ['SSO', 'disabled', 'No silent fallback']],
] as $name => [$file, $uri, $markers]) {
    $html = $render($file, $uri);
    if ($html === '') {
        throw new RuntimeException('CHAIN_AUTH_ENV_RENDER_EMPTY: ' . $name);
    }

    foreach ($markers as $marker) {
        if (!str_contains($html, $marker)) {
            throw new RuntimeException('CHAIN_AUTH_ENV_RENDER_MARKER_MISSING: ' . $name . ' => ' . $marker);
        }
    }
}

echo 'CHECK_P7_OPS_CHAIN_PAGES_RENDER=OK' . PHP_EOL;
echo 'P7_OPS_CHAIN_AUTH_ENV_CORE_SMOKE_OK' . PHP_EOL;
