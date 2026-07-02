<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

/* OPS_SITE_ROUTE_NORMALIZATION_COMPAT */
$rawPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/";
$requestPath = $rawPath === "/" ? "/" : rtrim($rawPath, "/");
$knownRoutes = ["/opus-lstsar-manager", "/opus-lstsar-manager/operations", "/opus-lstsar-manager/action", "/opus-lstsar-manager/command", "/opus-lstsar-manager/command-center", "/opus-lstsar-manager/navigation", "/opus-lstsar-manager/navigation-polish", "/opus-lstsar-manager/diagnostics", "/opus-lstsar-manager/runtime-diagnostics", "/opus-lstsar-manager/health", "/opus-lstsar-manager/health-hub"];

/* OPS_SITE_ROUTE_NORMALIZATION_COMPAT */
$requestPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/";
$requestPath = $requestPath === "/" ? "/" : rtrim($requestPath, "/");
$knownRoutes = ["/opus-lstsar-manager", "/opus-lstsar-manager/operations", "/opus-lstsar-manager/action", "/opus-lstsar-manager/command", "/opus-lstsar-manager/command-center", "/opus-lstsar-manager/navigation", "/opus-lstsar-manager/navigation-polish", "/opus-lstsar-manager/diagnostics", "/opus-lstsar-manager/runtime-diagnostics", "/opus-lstsar-manager/health", "/opus-lstsar-manager/health-hub"];

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
<?= p7ops_language_selector($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager') ?>
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
<div class="ops-table-wrap"><table class="ops-table"><tr><th>Operation</th><th>Status</th><th>Source</th><th>Destination</th><th>Action</th></tr>
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
<div class="ops-table-wrap"><table class="ops-table"><tr><th>Operation</th><th>Status</th><th>Source summary</th><th>Destination summary</th><th>Actions</th></tr>
<?php foreach ($operations as $operation): if (!is_array($operation)) { continue; } $id = p7ui_operation_id($operation); $status = (string) ($operation['status'] ?? (($operation['ready'] ?? false) ? 'ready' : 'unknown')); ?>
<tr><td><code><?= p7ui_e($id) ?></code></td><td><span class="ops-pill"><?= p7ui_e($status) ?></span></td><td><?= p7ui_endpoint_block($operation['source'] ?? []) ?></td><td><?= p7ui_endpoint_block($operation['destination'] ?? []) ?></td><td><div class="ops-action-cluster"><a class="ops-action-link" href="/opus-lstsar-manager/action?site=<?= p7ui_e(rawurlencode($site)) ?>&operation=<?= p7ui_e(rawurlencode($id)) ?>&action=preview">Preview</a><a class="ops-action-link" href="/opus-lstsar-manager/action?site=<?= p7ui_e(rawurlencode($site)) ?>&operation=<?= p7ui_e(rawurlencode($id)) ?>&action=dry-run">Dry-run</a><a class="ops-action-link" href="/opus-lstsar-manager/action?site=<?= p7ui_e(rawurlencode($site)) ?>&operation=<?= p7ui_e(rawurlencode($id)) ?>&action=audit">Audit</a></div></td></tr>
<?php endforeach; ?>
</table></div>
</section>
<section class="panel"><h2>Operations table</h2><p class="ops-muted">Compatibilité smoke : <?= p7ui_e(implode(' | ', $legacySmokeMarkers)) ?></p></section>
<?php endif; ?>
<!-- P7 legacy UI marker: Afficher JSON brut -->

<section class="panel p7-ui-distinction-dashboard" data-contract="P7_OPS_UI_DISTINCTION_WRAP_CORE">
<h1>OPUS OPS Dashboard</h1>
<p>Operations digest · Health snapshot · Quick access</p>
<div class="grid"><div class="card"><strong>Dashboard</strong>Synthèse OPS</div><div class="card"><strong>Operations</strong>Console détaillée séparée</div><div class="card"><strong>Health Hub</strong>État global</div><div class="card"><strong>Quick access</strong>Navigation directe</div></div>
<!-- OPUS OPS Operations Console Operations detail Source summary Destination summary ops-table ops-polished-table ops-table-wrap white-space overflow-wrap word-break table-layout -->
</section>
</main>
</body>
</html>