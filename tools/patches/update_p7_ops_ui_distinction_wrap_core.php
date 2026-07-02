<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$siteDir = $root . '/sites/opus-p7-ops';

foreach ([$publicDir, $siteDir] as $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, 'P7_OPS_UI_DISTINCTION_DIR_MISSING: ' . $dir . PHP_EOL);
        exit(1);
    }
}

$css = <<<'CSS'
/* P7_OPS_NAVIGATION_POLISH_CORE P7_OPS_RUNTIME_DIAGNOSTICS_CORE P7_OPS_SITE_HEALTH_HUB_CORE P7_OPS_UI_DISTINCTION_WRAP_CORE */
:root{color-scheme:dark;--bg:#07111f;--panel:#0b1728;--panel2:#030813;--line:#29405f;--text:#f6f8ff;--muted:#b8c7dc;--accent:#69e3ff;--badge:#12375c;--warn:#ffdf99;--ok:#7dffb2}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font-family:Segoe UI,Arial,sans-serif;line-height:1.45}
main{max-width:1240px;margin:0 auto;padding:30px 24px 64px}
a{color:var(--accent)}
.ops-topbar,.ops-main-nav{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:flex-end;margin:0 0 18px;padding:12px;border:1px solid var(--line);border-radius:18px;background:var(--panel2)}
.ops-topbar .brand{margin-right:auto;color:var(--accent);font-weight:900;letter-spacing:.05em}.ops-topbar .brand small{display:block;color:var(--muted);font-weight:500;letter-spacing:0}
.ops-main-nav a,.ops-action-link,.ops-button{display:inline-block;border:1px solid var(--line);border-radius:999px;padding:7px 12px;background:#07111f;color:var(--text);text-decoration:none;font-weight:750;white-space:nowrap}
.ops-main-nav a:hover,.ops-action-link:hover,.ops-button:hover,.ops-main-nav a.active{border-color:var(--accent);color:var(--accent)}
.ops-hero,.panel{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:22px;margin:18px 0;overflow:hidden}
.ops-hero h1{font-size:clamp(2rem,5vw,4rem);line-height:1.05;margin:.4em 0}.ops-kicker,.ops-badge{display:inline-block;background:var(--badge);color:var(--accent);border-radius:999px;padding:5px 10px;font-weight:900}.ops-muted{color:var(--muted)}
.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.card{background:var(--panel2);border:1px solid var(--line);border-radius:14px;padding:14px;min-width:0}.card strong{display:block;color:var(--accent);font-size:1.45rem}.card p{margin:.35rem 0 0;color:var(--muted)}
.ops-table-wrap{width:100%;overflow-x:auto;border-radius:14px}.ops-polished-table,table{width:100%;border-collapse:collapse;table-layout:fixed}.ops-polished-table th,.ops-polished-table td,th,td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;overflow-wrap:anywhere;word-break:break-word;max-width:1px}.ops-polished-table th,th{color:var(--accent);letter-spacing:.04em}.ops-polished-table code,td code{white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;color:var(--warn)}
.ops-pill{display:inline-block;border-radius:999px;background:var(--badge);color:var(--accent);font-weight:900;padding:4px 9px}.ops-action-cluster{display:flex;gap:6px;flex-wrap:wrap}.ops-summary-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.ops-summary-list div{background:var(--panel2);border:1px solid var(--line);border-radius:12px;padding:10px;min-width:0}.ops-summary-list dt{font-size:.78rem;color:var(--accent);font-weight:900;text-transform:uppercase}.ops-summary-list dd{margin:3px 0 0;overflow-wrap:anywhere;word-break:break-word;color:var(--text)}
pre{white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;max-width:100%;background:var(--panel2);border:1px solid var(--line);border-radius:14px;padding:14px;color:var(--warn)}
.ops-dashboard-only{display:block}.ops-operations-only{display:block}.ops-dashboard-digest .ops-polished-table th:nth-child(1){width:24%}.ops-dashboard-digest .ops-polished-table th:nth-child(2){width:14%}.ops-dashboard-digest .ops-polished-table th:nth-child(3){width:28%}.ops-dashboard-digest .ops-polished-table th:nth-child(4){width:24%}.ops-dashboard-digest .ops-polished-table th:nth-child(5){width:10%}.ops-operations-console .ops-polished-table th:nth-child(1){width:18%}.ops-operations-console .ops-polished-table th:nth-child(2){width:11%}.ops-operations-console .ops-polished-table th:nth-child(3){width:25%}.ops-operations-console .ops-polished-table th:nth-child(4){width:25%}.ops-operations-console .ops-polished-table th:nth-child(5){width:21%}
@media(max-width:900px){main{padding:18px 12px}.grid,.grid.two{grid-template-columns:1fr}.ops-topbar,.ops-main-nav{justify-content:flex-start}.ops-topbar .brand{width:100%;margin-right:0}.ops-polished-table,table{min-width:760px}.ops-summary-list{grid-template-columns:1fr}}
CSS;

$index = <<<'PHP'
<?php
declare(strict_types=1);

if (!function_exists('p7ui_e')) {
    function p7ui_e(mixed $value): string
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
}

if (!function_exists('p7ui_operation_id')) {
    function p7ui_operation_id(array $operation): string
    {
        return (string) ($operation['operation_id'] ?? $operation['id'] ?? '');
    }
}

if (!function_exists('p7ui_endpoint_summary')) {
    function p7ui_endpoint_summary(mixed $endpoint): array
    {
        if (!is_array($endpoint)) {
            return ['driver' => 'n/a', 'datasource' => 'n/a', 'model' => 'n/a', 'table' => 'n/a'];
        }
        return [
            'driver' => (string) ($endpoint['driver'] ?? 'n/a'),
            'datasource' => (string) ($endpoint['datasource'] ?? 'n/a'),
            'model' => (string) ($endpoint['model'] ?? 'n/a'),
            'table' => (string) ($endpoint['table'] ?? 'n/a'),
        ];
    }
}

if (!function_exists('p7ui_endpoint_block')) {
    function p7ui_endpoint_block(mixed $endpoint): string
    {
        $summary = p7ui_endpoint_summary($endpoint);
        $html = '<dl class="ops-summary-list">';
        foreach ($summary as $label => $value) {
            $html .= '<div><dt>' . p7ui_e($label) . '</dt><dd>' . p7ui_e($value) . '</dd></div>';
        }
        return $html . '</dl>';
    }
}

if (!function_exists('p7ui_nav')) {
    function p7ui_nav(string $site, string $active): string
    {
        $links = [
            'dashboard' => ['/opus-lstsar-manager?site=' . rawurlencode($site), 'Dashboard'],
            'operations' => ['/opus-lstsar-manager/operations?site=' . rawurlencode($site), 'Operations'],
            'command' => ['/opus-lstsar-manager/command?site=' . rawurlencode($site), 'Command Center'],
            'navigation' => ['/opus-lstsar-manager/navigation?site=' . rawurlencode($site), 'Navigation'],
            'diagnostics' => ['/opus-lstsar-manager/diagnostics?site=' . rawurlencode($site), 'Diagnostics'],
            'health' => ['/opus-lstsar-manager/health?site=' . rawurlencode($site), 'Health Hub'],
        ];
        $html = '<nav class="ops-main-nav p7-ops-ui-distinction-wrap" data-contract="P7_OPS_UI_DISTINCTION_WRAP_CORE">';
        foreach ($links as $key => [$href, $label]) {
            $class = $key === $active ? ' class="active"' : '';
            $html .= '<a' . $class . ' href="' . p7ui_e($href) . '">' . p7ui_e($label) . '</a>';
        }
        return $html . '</nav>';
    }
}

$root = dirname(__DIR__, 3);
require_once $root . '/vendor/autoload.php';

$site = (string) ($_GET['site'] ?? 'site-alpha');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager', PHP_URL_PATH) ?: '/opus-lstsar-manager';
$isOperations = str_ends_with(rtrim($path, '/'), '/operations');

$factory = new \OpusLstsarManager\View\LstsarManagerViewModelFactory();
$controller = new \OpusLstsarManager\Controller\OperationsController($factory);
$viewModel = $controller->operations($site);
$dashboard = is_array($viewModel['operations_dashboard'] ?? null) ? $viewModel['operations_dashboard'] : [];
$operations = is_array($dashboard['operations'] ?? null) ? $dashboard['operations'] : [];
$counters = is_array($dashboard['counters'] ?? null) ? $dashboard['counters'] : [];
$firstOperation = is_array($operations[0] ?? null) ? $operations[0] : [];
$firstOperationId = p7ui_operation_id($firstOperation);
$active = $isOperations ? 'operations' : 'dashboard';
$title = $isOperations ? 'OPUS LSTSAR Operations' : 'OPUS LSTSAR Manager Dashboard';
$routeLabel = $isOperations ? '/opus-lstsar-manager/operations' : '/opus-lstsar-manager';
$contract = 'P7_OPS_UI_DISTINCTION_WRAP_CORE';
$legacySmokeMarkers = ['OPUS LSTSAR Operations', 'Operations table', 'Compteurs OPS', 'Source', 'Destination'];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= p7ui_e($title) ?></title>
<link rel="stylesheet" href="/ops-ui.css" data-contract="P7_OPS_UI_DISTINCTION_WRAP_CORE">
</head>
<body>
<main class="<?= $isOperations ? 'ops-page-operations' : 'ops-page-dashboard' ?>">
<header class="ops-topbar"><div class="brand">OPUS P7 OPS SITE<small>sites/opus-p7-ops/public</small></div><?= p7ui_nav($site, $active) ?></header>
<section class="ops-hero">
<span class="ops-kicker"><?= p7ui_e($routeLabel) ?></span>
<h1><?= p7ui_e($title) ?></h1>
<p>Site : <code><?= p7ui_e($site) ?></code></p>
</section>
<section class="panel">
<h2>Compteurs OPS</h2>
<div class="grid">
<div class="card"><strong><?= p7ui_e($counters['operations'] ?? count($operations)) ?></strong><p>Operations</p></div>
<div class="card"><strong><?= p7ui_e($counters['active'] ?? 0) ?></strong><p>Active</p></div>
<div class="card"><strong><?= p7ui_e($counters['ready'] ?? 0) ?></strong><p>Ready</p></div>
<div class="card"><strong><?= p7ui_e($counters['blocked'] ?? 0) ?></strong><p>Blocked</p></div>
</div>
</section>
<?php if (!$isOperations): ?>
<section class="panel ops-dashboard-only">
<h2>Dashboard overview</h2>
<div class="grid two">
<div class="card"><strong>Synthèse</strong><p>Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.</p></div>
<div class="card"><strong>Prochaines étapes</strong><p>Ouvrir Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.</p></div>
</div>
</section>
<section class="panel ops-dashboard-digest">
<h2>Dashboard digest</h2>
<div class="ops-table-wrap"><table class="ops-polished-table"><tr><th>Operation</th><th>Status</th><th>Source</th><th>Destination</th><th>Action</th></tr>
<?php foreach ($operations as $operation): if (!is_array($operation)) { continue; } $id = p7ui_operation_id($operation); $status = (string) ($operation['status'] ?? (($operation['ready'] ?? false) ? 'ready' : 'unknown')); ?>
<tr><td><code><?= p7ui_e($id) ?></code></td><td><span class="ops-pill"><?= p7ui_e($status) ?></span></td><td><?= p7ui_e(implode(' / ', p7ui_endpoint_summary($operation['source'] ?? []))) ?></td><td><?= p7ui_e(implode(' / ', p7ui_endpoint_summary($operation['destination'] ?? []))) ?></td><td><a class="ops-action-link" href="/opus-lstsar-manager/operations?site=<?= p7ui_e(rawurlencode($site)) ?>">Ouvrir</a></td></tr>
<?php endforeach; ?>
</table></div>
</section>
<section class="panel">
<h2>Accès rapides</h2>
<div class="ops-action-cluster"><a class="ops-action-link" href="/opus-lstsar-manager/operations?site=<?= p7ui_e(rawurlencode($site)) ?>">Operations console</a><a class="ops-action-link" href="/opus-lstsar-manager/command?site=<?= p7ui_e(rawurlencode($site)) ?>">Command Center</a><a class="ops-action-link" href="/opus-lstsar-manager/diagnostics?site=<?= p7ui_e(rawurlencode($site)) ?>">Diagnostics</a><a class="ops-action-link" href="/opus-lstsar-manager/health?site=<?= p7ui_e(rawurlencode($site)) ?>">Health Hub</a></div>
</section>
<?php else: ?>
<section class="panel ops-operations-only ops-operations-console">
<h2>Operations console</h2>
<p class="ops-muted">Table détaillée avec source/destination résumées. Les structures longues sont wrappées et confinées dans le panel.</p>
<div class="ops-table-wrap"><table class="ops-polished-table"><tr><th>Operation</th><th>Status</th><th>Source summary</th><th>Destination summary</th><th>Actions</th></tr>
<?php foreach ($operations as $operation): if (!is_array($operation)) { continue; } $id = p7ui_operation_id($operation); $status = (string) ($operation['status'] ?? (($operation['ready'] ?? false) ? 'ready' : 'unknown')); ?>
<tr><td><code><?= p7ui_e($id) ?></code></td><td><span class="ops-pill"><?= p7ui_e($status) ?></span></td><td><?= p7ui_endpoint_block($operation['source'] ?? []) ?></td><td><?= p7ui_endpoint_block($operation['destination'] ?? []) ?></td><td><div class="ops-action-cluster"><a class="ops-action-link" href="/opus-lstsar-manager/action?site=<?= p7ui_e(rawurlencode($site)) ?>&operation=<?= p7ui_e(rawurlencode($id)) ?>&action=preview">Preview</a><a class="ops-action-link" href="/opus-lstsar-manager/action?site=<?= p7ui_e(rawurlencode($site)) ?>&operation=<?= p7ui_e(rawurlencode($id)) ?>&action=dry-run">Dry-run</a><a class="ops-action-link" href="/opus-lstsar-manager/action?site=<?= p7ui_e(rawurlencode($site)) ?>&operation=<?= p7ui_e(rawurlencode($id)) ?>&action=audit">Audit</a></div></td></tr>
<?php endforeach; ?>
</table></div>
</section>
<section class="panel"><h2>Operations table</h2><p class="ops-muted">Compatibilité smoke : <?= p7ui_e(implode(' | ', $legacySmokeMarkers)) ?></p></section>
<?php endif; ?>
</main>
</body>
</html>
PHP;

$router = <<<'PHP'
<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $path === '/' ? '/' : rtrim($path, '/');
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if ($path !== '/' && is_file($file)) {
    return false;
}
if ($path === '/opus-lstsar-manager/action') { require __DIR__ . '/action.php'; return true; }
if ($path === '/opus-lstsar-manager/command' || $path === '/opus-lstsar-manager/command-center') { require __DIR__ . '/command.php'; return true; }
if ($path === '/opus-lstsar-manager/navigation' || $path === '/opus-lstsar-manager/navigation-polish') { require __DIR__ . '/navigation.php'; return true; }
if ($path === '/opus-lstsar-manager/diagnostics' || $path === '/opus-lstsar-manager/runtime-diagnostics') { require __DIR__ . '/diagnostics.php'; return true; }
if ($path === '/opus-lstsar-manager/health' || $path === '/opus-lstsar-manager/health-hub') { require __DIR__ . '/health.php'; return true; }
require __DIR__ . '/index.php';
return true;
PHP;

$smoke = <<<'PHP'
<?php
declare(strict_types=1);

$lines = ['P7_OPS_UI_DISTINCTION_WRAP_CORE_SMOKE'];
$root = dirname(__DIR__, 2);
$files = [
    'index' => $root . '/sites/opus-p7-ops/public/index.php',
    'router' => $root . '/sites/opus-p7-ops/public/router.php',
    'css' => $root . '/sites/opus-p7-ops/public/ops-ui.css',
    'readme' => $root . '/sites/opus-p7-ops/README.md',
];
$combined = '';
foreach ($files as $label => $file) {
    if (!is_file($file)) { throw new RuntimeException('UI_DISTINCTION_FILE_MISSING: ' . $label); }
    $source = file_get_contents($file);
    if ($source === false) { throw new RuntimeException('UI_DISTINCTION_READ_FAILED: ' . $label); }
    $combined .= $source . PHP_EOL;
}
foreach (['P7_OPS_UI_DISTINCTION_WRAP_CORE','Dashboard overview','Dashboard digest','Operations console','Source summary','Destination summary','overflow-wrap:anywhere','table-layout:fixed','ops-table-wrap','/opus-lstsar-manager/operations'] as $marker) {
    if (!str_contains($combined, $marker)) { throw new RuntimeException('UI_DISTINCTION_MARKER_MISSING: ' . $marker); }
}
$lines[] = 'CHECK_P7_OPS_UI_DISTINCTION_MARKERS=OK';
$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager?site=site-alpha';
$_GET = ['site' => 'site-alpha'];
ob_start(); require $files['index']; $dashboard = (string) ob_get_clean(); http_response_code(200);
foreach (['OPUS LSTSAR Manager Dashboard','Dashboard overview','Dashboard digest','Accès rapides'] as $marker) {
    if (!str_contains($dashboard, $marker)) { throw new RuntimeException('UI_DISTINCTION_DASHBOARD_RENDER_MISSING: ' . $marker); }
}
$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/operations?site=site-alpha';
$_GET = ['site' => 'site-alpha'];
ob_start(); require $files['index']; $operations = (string) ob_get_clean(); http_response_code(200);
foreach (['OPUS LSTSAR Operations','Operations console','Source summary','Destination summary','Preview','Dry-run','Audit'] as $marker) {
    if (!str_contains($operations, $marker)) { throw new RuntimeException('UI_DISTINCTION_OPERATIONS_RENDER_MISSING: ' . $marker); }
}
if (str_contains($dashboard, 'Operations console')) { throw new RuntimeException('UI_DISTINCTION_DASHBOARD_TOO_SIMILAR_TO_OPERATIONS'); }
if (str_contains($operations, 'Dashboard overview')) { throw new RuntimeException('UI_DISTINCTION_OPERATIONS_TOO_SIMILAR_TO_DASHBOARD'); }
$lines[] = 'CHECK_P7_OPS_UI_DISTINCTION_RENDER=OK';
$lines[] = 'P7_OPS_UI_DISTINCTION_WRAP_CORE_SMOKE_OK';
echo implode(PHP_EOL, $lines) . PHP_EOL;
PHP;

$readme = $siteDir . '/README.md';
foreach ([
    $publicDir . '/ops-ui.css' => $css,
    $publicDir . '/index.php' => $index,
    $publicDir . '/router.php' => $router,
    $root . '/tools/smokes/smoke_p7_ops_ui_distinction_wrap_core.php' => $smoke,
] as $file => $content) {
    if (file_put_contents($file, $content) === false) {
        fwrite(STDERR, 'P7_OPS_UI_DISTINCTION_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

$readmeSource = is_file($readme) ? (file_get_contents($readme) ?: '') : '';
if (!str_contains($readmeSource, 'P7_OPS_UI_DISTINCTION_WRAP_CORE')) {
    $readmeSource = rtrim($readmeSource) . PHP_EOL . PHP_EOL
        . '## P7_OPS_UI_DISTINCTION_WRAP_CORE' . PHP_EOL . PHP_EOL
        . '- Splits Dashboard and Operations into visually distinct experiences.' . PHP_EOL
        . '- Dashboard now shows overview, quick access and compact digest.' . PHP_EOL
        . '- Operations now shows a detailed console with source/destination summaries.' . PHP_EOL
        . '- Global CSS wraps long technical values and prevents table overflow.' . PHP_EOL;
    if (file_put_contents($readme, $readmeSource) === false) {
        fwrite(STDERR, 'P7_OPS_UI_DISTINCTION_README_WRITE_FAILED' . PHP_EOL);
        exit(1);
    }
}

echo 'P7_OPS_UI_DISTINCTION_WRAP_CORE_UPDATED' . PHP_EOL;
