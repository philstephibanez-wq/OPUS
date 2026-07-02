<?php
declare(strict_types=1);

echo "P7_OPS_PROFILER_CHAIN_CLEANUP_CORE_SMOKE\n";

$root = dirname(__DIR__, 2);
$public = $root . '/sites/opus-p7-ops/public';
$config = $root . '/sites/opus-p7-ops/config';
$logDir = $root . '/var/logs/opus_lstsar-manager';

$files = [
    $public . '/language.php',
    $public . '/router.php',
    $public . '/profiler.php',
    $public . '/profiler-exit.php',
    $public . '/chain.php',
    $public . '/ops-ui.css',
    $config . '/environment.prod.example.php',
    $root . '/sites/opus-p7-ops/README.md',
    $root . '/var/logs/.gitignore',
    $logDir . '/.gitkeep',
];

foreach ($files as $file) {
    if (!is_file($file)) { throw new RuntimeException('CLEANUP_FILE_MISSING: ' . $file); }
}

$combined = '';
foreach ($files as $file) {
    $s = file_get_contents($file);
    if (!is_string($s)) { throw new RuntimeException('CLEANUP_READ_FAILED: ' . $file); }
    $combined .= $s . "\n";
}

foreach ([
    'P7_OPS_PROFILER_CHAIN_CLEANUP_CORE',
    'p7ops_clean_profiler_boot_once',
    'p7ops_clean_profiler_enabled',
    'p7ops_clean_profiler_disable',
    'p7ops_clean_chain_steps',
    'p7ops_clean_toolbar_html',
    'p7ops_clean_is_static_path',
    '/opus-lstsar-manager/profiler',
    '/opus-lstsar-manager/profiler/exit',
    'return false;',
    'favicon',
    'Auth / SSO',
    'RBAC / Policies',
    'Database / Tables',
    'ODBC Manager',
    'LSTSAR',
    'Logs / Profiler',
    'display:none!important',
    '.opus-profiler-toolbar',
    'environment.prod.example.php',
] as $marker) {
    if (!str_contains($combined, $marker)) { throw new RuntimeException('CLEANUP_MARKER_MISSING: ' . $marker); }
}

if (str_contains(file_get_contents($public . '/language.php') ?: '', 'hrtime(true)')) {
    throw new RuntimeException('CLEANUP_HRTIME_STILL_PRESENT');
}

echo "CHECK_P7_OPS_PROFILER_CHAIN_CLEANUP_MARKERS=OK\n";

require_once $public . '/language.php';

$_GET = ['site' => 'site-alpha', 'lang' => 'fr', 'profiler' => '1'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/operations?site=site-alpha&lang=fr&profiler=1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'P7_OPS_PROFILER_CHAIN_CLEANUP_CORE_SMOKE';

$p = p7ops_clean_profiler_collect('smoke');
p7ops_clean_profiler_store($p);
$toolbar = p7ops_clean_toolbar_html($p);

foreach (['OPUS Profiler','Chain','Details','Exit','P7_OPS_PROFILER_CHAIN_CLEANUP_CORE'] as $marker) {
    if (!str_contains($toolbar, $marker)) { throw new RuntimeException('CLEANUP_TOOLBAR_MARKER_MISSING: ' . $marker); }
}

echo "CHECK_P7_OPS_PROFILER_CHAIN_CLEANUP_TOOLBAR=OK\n";

$keys = array_map(static fn(array $s): string => (string) $s['key'], p7ops_clean_chain_steps());
foreach (['auth','rbac','fsm','cl','models','database','odbc','lstsar','actions','observability'] as $key) {
    if (!in_array($key, $keys, true)) { throw new RuntimeException('CLEANUP_CHAIN_KEY_MISSING: ' . $key); }
}

echo "CHECK_P7_OPS_PROFILER_CHAIN_CLEANUP_CHAIN=OK\n";

$render = static function (string $file, string $uri): string {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = ['site' => 'site-alpha', 'lang' => 'fr', 'profiler' => '1'];
    ob_start();
    (static function (string $__file): void { require $__file; })($file);
    $html = ob_get_clean();
    return is_string($html) ? $html : '';
};

$profiler = $render($public . '/profiler.php', '/opus-lstsar-manager/profiler?site=site-alpha&lang=fr');
foreach (['OPUS Web Profiler','profiler typé session','Request','Performance','Session','Auth / SSO','Chaîne fonctionnelle complète','History'] as $marker) {
    if (!str_contains($profiler, $marker)) { throw new RuntimeException('CLEANUP_PROFILER_PAGE_MARKER_MISSING: ' . $marker); }
}

$chain = $render($public . '/chain.php', '/opus-lstsar-manager/chain?site=site-alpha&lang=fr');
foreach (['Chaîne complète LSTSAR','Auth / SSO','RBAC / Policies','FSM','CL','Models','Database / Tables','ODBC Manager','LSTSAR','Logs / Profiler'] as $marker) {
    if (!str_contains($chain, $marker)) { throw new RuntimeException('CLEANUP_CHAIN_PAGE_MARKER_MISSING: ' . $marker); }
}

echo "CHECK_P7_OPS_PROFILER_CHAIN_CLEANUP_PAGES=OK\n";

$profilerLog = $logDir . '/profiler.log';
if (!is_file($profilerLog)) { throw new RuntimeException('CLEANUP_PROFILER_LOG_MISSING'); }
$tail = file_get_contents($profilerLog, false, null, max(0, (int) filesize($profilerLog) - 4096));
if (!is_string($tail) || !str_contains($tail, '"event":"typed_profile"')) {
    throw new RuntimeException('CLEANUP_PROFILER_LOG_EVENT_MISSING');
}

echo "CHECK_P7_OPS_PROFILER_CHAIN_CLEANUP_LOG=OK\n";
echo "P7_OPS_PROFILER_CHAIN_CLEANUP_CORE_SMOKE_OK\n";
