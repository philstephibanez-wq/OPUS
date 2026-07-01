<?php
declare(strict_types=1);

function ops_action_e(mixed $value): string
{
    if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

$site = (string) ($_GET['site'] ?? 'site-alpha');
$operationId = (string) ($_GET['operation'] ?? '');
$action = (string) ($_GET['action'] ?? 'preview');
$allowedActions = ['preview' => true, 'dry-run' => true, 'audit' => true];

$status = 200;
$title = 'OPUS OPS Action';
$error = null;
$selectedOperation = null;
$dashboard = [];
$operations = [];

if (!isset($allowedActions[$action])) {
    $status = 400;
    $title = 'OPUS OPS Action rejected';
    $error = 'Unknown action: ' . $action;
} else {
    $factory = new \OpusLstsarManager\View\LstsarManagerViewModelFactory();
    $controller = new \OpusLstsarManager\Controller\OperationsController($factory);
    $viewModel = $controller->operations($site);

    $dashboard = is_array($viewModel['operations_dashboard'] ?? null) ? $viewModel['operations_dashboard'] : [];
    $operations = is_array($dashboard['operations'] ?? null) ? $dashboard['operations'] : [];

    foreach ($operations as $candidateOperation) {
        if (!is_array($candidateOperation)) {
            continue;
        }

        $candidateId = (string) ($candidateOperation['operation_id'] ?? $candidateOperation['id'] ?? '');
        if ($candidateId === $operationId) {
            $selectedOperation = $candidateOperation;
            break;
        }
    }

    if ($selectedOperation === null) {
        $status = 404;
        $title = 'OPUS OPS Operation not found';
        $error = 'Unknown operation: ' . $operationId;
    }
}

$response = [
    'contract' => 'OPUS_LSTSAR_MANAGER_OPERATION_ACTION_V1',
    'action' => $action,
    'operation_id' => $operationId,
    'site' => $site,
    'mode' => 'controlled_preview',
    'side_effects' => false,
    'message' => 'Action OPS simulated without write or destructive execution.',
    'operation' => $selectedOperation,
    'error' => $error,
];

http_response_code($status);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= ops_action_e($title) ?></title>
<style>
body{margin:0;background:#07111f;color:#e7eefc;font-family:Segoe UI,Arial,sans-serif}
main{max-width:1180px;margin:0 auto;padding:32px}
a{color:#69e3ff}
.panel{background:#0b1728;border:1px solid #29405f;border-radius:18px;padding:20px;margin:18px 0}
.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:18px}
.metric{background:#030813;border:1px solid #29405f;border-radius:16px;padding:16px}
.metric strong{display:block;font-size:1.4rem;color:#69e3ff}
pre{white-space:pre-wrap;word-break:break-word;background:#030813;border:1px solid #29405f;border-radius:16px;padding:18px;color:#ffdf99}
.badge{display:inline-block;border-radius:999px;padding:6px 10px;background:#12375c;color:#69e3ff;font-weight:900}
@media(max-width:900px){.metrics{grid-template-columns:1fr}}
</style>
</head>
<body>
<main>
<p><a href="/opus-lstsar-manager/operations?site=<?= ops_action_e($site) ?>">Retour operations</a></p>
<section class="panel">
<h1>Action OPS controlee</h1>
<p><span class="badge">OPUS_LSTSAR_MANAGER_OPERATION_ACTION_V1</span></p>
<div class="metrics">
<div class="metric"><strong><?= ops_action_e($action) ?></strong><span>Action</span></div>
<div class="metric"><strong><?= ops_action_e($operationId) ?></strong><span>Operation</span></div>
<div class="metric"><strong>controlled_preview</strong><span>Mode</span></div>
<div class="metric"><strong>false</strong><span>Side effects</span></div>
</div>
</section>
<section class="panel">
<h2>Resultat controle</h2>
<pre><?= ops_action_e(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></pre>
</section>
</main>
</body>
</html>
