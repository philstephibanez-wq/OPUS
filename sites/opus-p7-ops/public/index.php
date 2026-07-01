<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

function ops_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $rawPath === '/' ? '/' : rtrim($rawPath, '/');
$site = (string) ($_GET['site'] ?? 'site-alpha');
$title = 'OPUS P7 OPS SITE';
$data = [];
$error = '';
$status = 200;

try {
    $factory = new \OpusLstsarManager\View\LstsarManagerViewModelFactory();

    if ($path === '/' || $path === '/opus-lstsar-manager') {
        $title = 'OPUS LSTSAR Manager Dashboard';
        $data = (new \OpusLstsarManager\Controller\DashboardController($factory))->dashboard($site);
    } elseif ($path === '/opus-lstsar-manager/operations') {
        $title = 'OPUS LSTSAR Operations';
        $data = (new \OpusLstsarManager\Controller\OperationsController($factory))->operations($site);
    } else {
        $status = 404;
        $title = 'OPUS OPS ROUTE NOT FOUND';
        $error = 'Route inconnue : ' . $path;
    }
} catch (\Throwable $exception) {
    $status = 500;
    $title = 'OPUS OPS RENDER FAILED';
    $error = $exception::class . ': ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString();
}

http_response_code($status);
header('Content-Type: text/html; charset=utf-8');

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $json = '{}';
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= ops_e($title) ?></title>
<style>
body{margin:0;background:#07111f;color:#f6f8ff;font-family:Segoe UI,Arial,sans-serif}
.shell{width:min(1180px,calc(100% - 48px));margin:0 auto;padding:36px 0}
.top{display:flex;justify-content:space-between;gap:16px;align-items:center;border-bottom:1px solid #29405f;padding-bottom:18px}
.brand{font-weight:900;color:#69e3ff;letter-spacing:.08em}
.nav a{color:#f6f8ff;border:1px solid #29405f;border-radius:999px;padding:9px 14px;text-decoration:none;margin-left:8px}
.panel{margin-top:24px;background:#0d1a2d;border:1px solid #29405f;border-radius:22px;padding:24px}
h1{font-size:clamp(2rem,5vw,4rem);margin:.25em 0}
.muted{color:#b6c5dc}
.badge{display:inline-block;color:#07111f;background:#69e3ff;border-radius:999px;padding:6px 10px;font-weight:900}
pre{background:#030813;border:1px solid #29405f;border-radius:14px;padding:18px;overflow:auto;color:#ffdf99}
code{color:#ffdf99}
.ops-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:18px}
.ops-metric{background:#030813;border:1px solid #29405f;border-radius:16px;padding:16px}
.ops-metric strong{display:block;font-size:2rem;color:#69e3ff}
.ops-metric span{color:#b6c5dc}
.ops-table{width:100%;border-collapse:collapse;margin-top:18px}
.ops-table th,.ops-table td{border-bottom:1px solid #29405f;padding:12px;text-align:left;vertical-align:top}
.ops-table th{color:#69e3ff;font-size:.85rem;text-transform:uppercase;letter-spacing:.08em}
.ops-status{display:inline-block;border-radius:999px;padding:5px 9px;font-weight:900;background:#12375c;color:#69e3ff}
.ops-actions{display:flex;gap:8px;flex-wrap:wrap}
.ops-actions a{color:#f6f8ff;border:1px solid #29405f;border-radius:999px;padding:7px 10px;text-decoration:none;font-weight:700}
details summary{cursor:pointer;color:#69e3ff;font-weight:900}
@media(max-width:900px){.ops-metrics{grid-template-columns:1fr}.ops-table{display:block;overflow:auto}}
</style>
<link rel="stylesheet" href="/ops-ui.css" data-contract="P7_OPS_NAVIGATION_POLISH_CORE">
</head>
<body>
<main class="shell">
<div class="top">
<div>
<div class="brand">OPUS P7 OPS SITE</div>
<div class="muted">sites/opus-p7-ops/public</div>
</div>
<nav class="nav">
<a href="/opus-lstsar-manager">Dashboard</a>
<a href="/opus-lstsar-manager/operations">Operations</a>
</nav>
</div>
<section class="panel">
<span class="badge"><?= ops_e($path) ?></span>
<h1><?= ops_e($title) ?></h1>
<p class="muted">Site : <code><?= ops_e($site) ?></code></p>
</section>
<?php if ($error !== ''): ?>
<section class="panel">
<h2>Erreur</h2>
<pre><?= ops_e($error) ?></pre>
</section>
<?php else: ?>
<?php
$dashboard = is_array($data['operations_dashboard'] ?? null) ? $data['operations_dashboard'] : [];
$counters = is_array($dashboard['counters'] ?? null) ? $dashboard['counters'] : [];
$operations = is_array($dashboard['operations'] ?? null) ? $dashboard['operations'] : [];
?>
<section class="panel">
<h2>Compteurs OPS</h2>
<div class="ops-metrics">
<div class="ops-metric"><strong><?= ops_e($counters['operations'] ?? 0) ?></strong><span>Operations</span></div>
<div class="ops-metric"><strong><?= ops_e($counters['active'] ?? 0) ?></strong><span>Active</span></div>
<div class="ops-metric"><strong><?= ops_e($counters['ready'] ?? 0) ?></strong><span>Ready</span></div>
<div class="ops-metric"><strong><?= ops_e($counters['blocked'] ?? 0) ?></strong><span>Blocked</span></div>
</div>
</section>
<section class="panel">
<h2>Operations</h2>
<?php if ($operations === []): ?>
<p class="muted">Aucune opération déclarée pour ce site.</p>
<?php else: ?>
<table class="ops-table">
<thead>
<tr>
<th>Operation</th>
<th>Type</th>
<th>Status</th>
<th>Source</th>
<th>Destination</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($operations as $operation): ?>
<?php
$operationId = (string) ($operation['operation_id'] ?? $operation['id'] ?? '');
$type = (string) ($operation['type'] ?? $operation['kind'] ?? 'operation');
$status = (string) ($operation['status'] ?? ($operation['ready'] ?? false ? 'ready' : 'unknown'));
$source = $operation['source'] ?? ($operation['source_id'] ?? '');
$destination = $operation['destination'] ?? ($operation['destination_id'] ?? '');
?>
<tr>
<td><code><?= ops_e($operationId) ?></code></td>
<td><?= ops_e($type) ?></td>
<td><span class="ops-status"><?= ops_e($status) ?></span></td>
<td><?= ops_e(is_array($source) ? json_encode($source, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $source) ?></td>
<td><?= ops_e(is_array($destination) ? json_encode($destination, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $destination) ?></td>
<td><div class="ops-actions"><a href="/opus-lstsar-manager/action?site=<?= ops_e($site) ?>&operation=<?= ops_e($operationId) ?>&action=preview">Preview</a><a href="/opus-lstsar-manager/action?site=<?= ops_e($site) ?>&operation=<?= ops_e($operationId) ?>&action=dry-run">Dry-run</a><a href="/opus-lstsar-manager/action?site=<?= ops_e($site) ?>&operation=<?= ops_e($operationId) ?>&action=audit">Audit</a></div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</section>
<section class="panel">
<details>
<summary>Afficher JSON brut</summary>
<pre><?= ops_e($json) ?></pre>
</details>
</section>
<?php endif; ?>
</main>
</body>
</html>
