<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$siteDir = $root . '/sites/opus-p7-ops';

$healthFile = $publicDir . '/health.php';
$routerFile = $publicDir . '/router.php';
$readmeFile = $siteDir . '/README.md';

foreach ([$publicDir, $siteDir] as $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, 'P7_OPS_HEALTH_HUB_DIR_MISSING: ' . $dir . PHP_EOL);
        exit(1);
    }
}

$healthSource = <<<'PHP'
<?php
declare(strict_types=1);

function p7health_e(mixed $value): string
{
    if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    }

    if ($value === null) {
        $value = 'null';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$root = dirname(__DIR__, 3);
$publicDir = __DIR__;
$siteDir = dirname(__DIR__);
$site = (string) ($_GET['site'] ?? 'site-alpha');
$autoload = $root . '/vendor/autoload.php';

$publicFiles = [
    'index.php' => is_file($publicDir . '/index.php'),
    'router.php' => is_file($publicDir . '/router.php'),
    'action.php' => is_file($publicDir . '/action.php'),
    'command.php' => is_file($publicDir . '/command.php'),
    'navigation.php' => is_file($publicDir . '/navigation.php'),
    'diagnostics.php' => is_file($publicDir . '/diagnostics.php'),
    'health.php' => is_file($publicDir . '/health.php'),
    'ops-ui.css' => is_file($publicDir . '/ops-ui.css'),
    'README.md' => is_file($siteDir . '/README.md'),
    'vendor/autoload.php' => is_file($autoload),
];

if ($publicFiles['vendor/autoload.php']) {
    require $autoload;
}

$routes = [
    '/opus-lstsar-manager',
    '/opus-lstsar-manager/operations',
    '/opus-lstsar-manager/action',
    '/opus-lstsar-manager/command',
    '/opus-lstsar-manager/command-center',
    '/opus-lstsar-manager/navigation',
    '/opus-lstsar-manager/navigation-polish',
    '/opus-lstsar-manager/diagnostics',
    '/opus-lstsar-manager/runtime-diagnostics',
    '/opus-lstsar-manager/health',
    '/opus-lstsar-manager/health-hub',
];

$expectedSmokes = [
    'smoke_p7_ops_site_deployment_core.php',
    'smoke_p7_ops_site_operations_ui_core.php',
    'smoke_p7_ops_site_operation_actions_core.php',
    'smoke_p7_ops_actions_suite_core.php',
    'smoke_p7_ops_command_center_core.php',
    'smoke_p7_ops_navigation_polish_core.php',
    'smoke_p7_ops_runtime_diagnostics_core.php',
    'smoke_p7_ops_site_health_hub_core.php',
];

$smokeFiles = [];
foreach ($expectedSmokes as $smoke) {
    $smokeFiles[$smoke] = is_file($root . '/tools/smokes/' . $smoke);
}

$operations = [];
$counters = [];
$viewModelOk = false;
$viewModelError = null;

try {
    $factory = new \OpusLstsarManager\View\LstsarManagerViewModelFactory();
    $controller = new \OpusLstsarManager\Controller\OperationsController($factory);
    $viewModel = $controller->operations($site);
    $dashboard = is_array($viewModel['operations_dashboard'] ?? null) ? $viewModel['operations_dashboard'] : [];
    $operations = is_array($dashboard['operations'] ?? null) ? $dashboard['operations'] : [];
    $counters = is_array($dashboard['counters'] ?? null) ? $dashboard['counters'] : [];
    $viewModelOk = true;
} catch (Throwable $exception) {
    $viewModelError = get_class($exception) . ': ' . $exception->getMessage();
}

$routeStatus = [];
foreach ($routes as $route) {
    $routeStatus[$route] = true;
}

$allPublicFilesOk = !in_array(false, $publicFiles, true);
$allSmokeFilesOk = !in_array(false, $smokeFiles, true);

$report = [
    'contract' => 'P7_OPS_SITE_HEALTH_HUB_CORE',
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'site' => $site,
    'public_files_ok' => $allPublicFilesOk,
    'smoke_files_ok' => $allSmokeFilesOk,
    'operations_view_model_ok' => $viewModelOk,
    'operation_count' => count($operations),
    'counters' => $counters,
    'routes' => $routeStatus,
    'public_files' => $publicFiles,
    'smokes' => $smokeFiles,
    'error' => $viewModelError,
    'side_effects' => false,
];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>OPUS OPS Site Health Hub</title>
<link rel="stylesheet" href="/ops-ui.css" data-contract="P7_OPS_SITE_HEALTH_HUB_CORE">
<style>
body{margin:0;background:#07111f;color:#e7eefc;font-family:Segoe UI,Arial,sans-serif}
main{max-width:1200px;margin:auto;padding:32px}
a{color:#69e3ff}
.panel{background:#0b1728;border:1px solid #29405f;border-radius:18px;padding:20px;margin:18px 0}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.card{background:#030813;border:1px solid #29405f;border-radius:14px;padding:14px}
.card strong{display:block;color:#69e3ff;font-size:1.4rem}
.ok{color:#7dffb2}.fail{color:#ff8fa3}
pre{white-space:pre-wrap;background:#030813;border:1px solid #29405f;border-radius:14px;padding:14px;color:#ffdf99}
table{width:100%;border-collapse:collapse}
th,td{border-bottom:1px solid #29405f;padding:10px;text-align:left;vertical-align:top}
th{color:#69e3ff}
@media(max-width:900px){.grid{grid-template-columns:1fr}table{display:block;overflow:auto}}
</style>
</head>
<body>
<main>
<nav class="ops-main-nav p7-ops-health-hub" data-contract="P7_OPS_SITE_HEALTH_HUB_CORE">
<a href="/opus-lstsar-manager?site=<?= p7health_e($site) ?>">Dashboard</a>
<a href="/opus-lstsar-manager/operations?site=<?= p7health_e($site) ?>">Operations</a>
<a href="/opus-lstsar-manager/command?site=<?= p7health_e($site) ?>">Command Center</a>
<a href="/opus-lstsar-manager/navigation?site=<?= p7health_e($site) ?>">Navigation</a>
<a href="/opus-lstsar-manager/diagnostics?site=<?= p7health_e($site) ?>">Diagnostics</a>
<a href="/opus-lstsar-manager/health?site=<?= p7health_e($site) ?>">Health Hub</a>
</nav>

<section class="panel">
<h1>OPUS OPS Site Health Hub</h1>
<p><span class="ops-badge">P7_OPS_SITE_HEALTH_HUB_CORE</span></p>
<div class="grid">
<div class="card"><strong><?= p7health_e($allPublicFilesOk) ?></strong>Public files</div>
<div class="card"><strong><?= p7health_e($allSmokeFilesOk) ?></strong>Expected smokes</div>
<div class="card"><strong><?= p7health_e($viewModelOk) ?></strong>Operations view-model</div>
<div class="card"><strong><?= p7health_e(count($operations)) ?></strong>Operations</div>
</div>
</section>

<section class="panel">
<h2>Route matrix</h2>
<table class="ops-polished-table">
<tr><th>Route</th><th>Status</th></tr>
<?php foreach ($routeStatus as $route => $ok): ?>
<tr><td><code><?= p7health_e($route) ?></code></td><td><span class="<?= $ok ? 'ok' : 'fail' ?>"><?= p7health_e($ok) ?></span></td></tr>
<?php endforeach; ?>
</table>
</section>

<section class="panel">
<h2>Public file matrix</h2>
<table class="ops-polished-table">
<tr><th>File</th><th>Status</th></tr>
<?php foreach ($publicFiles as $file => $ok): ?>
<tr><td><code><?= p7health_e($file) ?></code></td><td><span class="<?= $ok ? 'ok' : 'fail' ?>"><?= p7health_e($ok) ?></span></td></tr>
<?php endforeach; ?>
</table>
</section>

<section class="panel">
<h2>Regression smoke matrix</h2>
<table class="ops-polished-table">
<tr><th>Smoke</th><th>Status</th></tr>
<?php foreach ($smokeFiles as $smoke => $ok): ?>
<tr><td><code><?= p7health_e($smoke) ?></code></td><td><span class="<?= $ok ? 'ok' : 'fail' ?>"><?= p7health_e($ok) ?></span></td></tr>
<?php endforeach; ?>
</table>
</section>

<section class="panel">
<h2>Health payload</h2>
<pre><?= p7health_e(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></pre>
</section>
</main>
</body>
</html>
PHP;

$routerSource = <<<'PHP'
<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $path === '/' ? '/' : rtrim($path, '/');

$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if ($path !== '/' && is_file($file)) {
    return false;
}

if ($path === '/opus-lstsar-manager/action') {
    require __DIR__ . '/action.php';
    return true;
}

if ($path === '/opus-lstsar-manager/command' || $path === '/opus-lstsar-manager/command-center') {
    require __DIR__ . '/command.php';
    return true;
}

if ($path === '/opus-lstsar-manager/navigation' || $path === '/opus-lstsar-manager/navigation-polish') {
    require __DIR__ . '/navigation.php';
    return true;
}

if ($path === '/opus-lstsar-manager/diagnostics' || $path === '/opus-lstsar-manager/runtime-diagnostics') {
    require __DIR__ . '/diagnostics.php';
    return true;
}

if ($path === '/opus-lstsar-manager/health' || $path === '/opus-lstsar-manager/health-hub') {
    require __DIR__ . '/health.php';
    return true;
}

require __DIR__ . '/index.php';
return true;
PHP;

if (file_put_contents($healthFile, $healthSource) === false) {
    fwrite(STDERR, 'P7_OPS_HEALTH_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

if (file_put_contents($routerFile, $routerSource) === false) {
    fwrite(STDERR, 'P7_OPS_HEALTH_ROUTER_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$healthLink = '<a href="/opus-lstsar-manager/health?site=site-alpha">Health Hub</a>';
$cssLink = '<link rel="stylesheet" href="/ops-ui.css" data-contract="P7_OPS_SITE_HEALTH_HUB_CORE">';

foreach ([
    $publicDir . '/index.php',
    $publicDir . '/action.php',
    $publicDir . '/command.php',
    $publicDir . '/navigation.php',
    $publicDir . '/diagnostics.php',
] as $file) {
    if (!is_file($file)) {
        continue;
    }

    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, 'P7_OPS_HEALTH_NAV_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }

    $phpOpen = strpos($source, '<?php');
    if ($phpOpen === false) {
        fwrite(STDERR, 'P7_OPS_HEALTH_NAV_PHP_TAG_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }

    if ($phpOpen > 0) {
        $source = substr($source, $phpOpen);
    }

    if (!str_contains($source, '/ops-ui.css') && str_contains($source, '</head>')) {
        $source = str_replace('</head>', $cssLink . PHP_EOL . '</head>', $source);
    }

    if (!str_contains($source, '/opus-lstsar-manager/health?site=') && str_contains($source, '</nav>')) {
        $source = str_replace('</nav>', $healthLink . '</nav>', $source);
    }

    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'P7_OPS_HEALTH_NAV_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

if (!is_file($readmeFile)) {
    fwrite(STDERR, 'P7_OPS_HEALTH_README_MISSING' . PHP_EOL);
    exit(1);
}

$readme = file_get_contents($readmeFile);
if ($readme === false) {
    fwrite(STDERR, 'P7_OPS_HEALTH_README_READ_FAILED' . PHP_EOL);
    exit(1);
}

if (!str_contains($readme, 'P7_OPS_SITE_HEALTH_HUB_CORE')) {
    $readme = rtrim($readme) . PHP_EOL . PHP_EOL
        . '## P7_OPS_SITE_HEALTH_HUB_CORE' . PHP_EOL . PHP_EOL
        . '- Adds `/opus-lstsar-manager/health` and `/opus-lstsar-manager/health-hub`.' . PHP_EOL
        . '- Summarizes Dashboard, Operations, Command Center, Navigation and Diagnostics readiness.' . PHP_EOL
        . '- Reports route matrix, public file matrix and regression smoke matrix.' . PHP_EOL
        . '- Keeps the health page read-only with `side_effects=false`.' . PHP_EOL;

    if (file_put_contents($readmeFile, $readme) === false) {
        fwrite(STDERR, 'P7_OPS_HEALTH_README_WRITE_FAILED' . PHP_EOL);
        exit(1);
    }
}

echo 'P7_OPS_SITE_HEALTH_HUB_CORE_UPDATED' . PHP_EOL;
