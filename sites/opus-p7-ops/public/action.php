<?php
declare(strict_types=1);

if (!function_exists('ops_action_e')) {
    function ops_action_e(mixed $value): string
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

if (!function_exists('ops_action_json')) {
    function ops_action_json(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

$site = (string) ($_GET['site'] ?? 'site-alpha');
$operationId = (string) ($_GET['operation'] ?? '');
$action = (string) ($_GET['action'] ?? 'preview');

$actionProfiles = [
    'preview' => [
        'label' => 'Preview',
        'mode' => 'controlled_preview',
        'message' => 'Preview only: read-only inspection without execution.',
    ],
    'dry-run' => [
        'label' => 'Dry-run',
        'mode' => 'controlled_dry_run',
        'message' => 'Dry-run only: execution plan simulated without write or destructive side effect.',
    ],
    'audit' => [
        'label' => 'Audit',
        'mode' => 'controlled_audit',
        'message' => 'Audit only: diagnostic report preview without changing the source or destination.',
    ],
];

$status = 200;
$title = 'OPUS OPS Action';
$error = null;
$selectedOperation = null;
$dashboard = [];
$operations = [];

if (!array_key_exists($action, $actionProfiles)) {
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

$profile = $actionProfiles[$action] ?? [
    'label' => $action,
    'mode' => 'rejected',
    'message' => 'Rejected action.',
];

$response = [
    'contract' => 'OPUS_LSTSAR_MANAGER_OPERATION_ACTION_V1',
    'action' => $action,
    'action_label' => $profile['label'],
    'operation_id' => $operationId,
    'site' => $site,
    'mode' => $profile['mode'],
    'side_effects' => false,
    'status' => $status,
    'error' => $error,
    'message' => $error ?? $profile['message'],
    'operation' => $selectedOperation,
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
.metric strong{display:block;font-size:1.25rem;color:#69e3ff;word-break:break-word}
.metric span{color:#b6c5dc}
pre{white-space:pre-wrap;word-break:break-word;background:#030813;border:1px solid #29405f;border-radius:16px;padding:18px;color:#ffdf99}
.badge{display:inline-block;border-radius:999px;padding:6px 10px;background:#12375c;color:#69e3ff;font-weight:900}
.error{border-color:#8a3750}
@media(max-width:900px){.metrics{grid-template-columns:1fr}}
</style>
<link rel="stylesheet" href="/ops-ui.css" data-contract="P7_OPS_NAVIGATION_POLISH_CORE">
</head>
<body>
<main>
<p><a href="/opus-lstsar-manager/operations?site=<?= ops_action_e($site) ?>">Retour operations</a></p>

<section class="panel">
<h1>Action OPS controlee</h1>
<p><span class="badge">OPUS_LSTSAR_MANAGER_OPERATION_ACTION_V1</span></p>
<div class="metrics">
<div class="metric"><strong><?= ops_action_e($profile['label']) ?></strong><span>Action</span></div>
<div class="metric"><strong><?= ops_action_e($operationId) ?></strong><span>Operation</span></div>
<div class="metric"><strong><?= ops_action_e($profile['mode']) ?></strong><span>Mode</span></div>
<div class="metric"><strong>false</strong><span>side_effects</span></div>
</div>
</section>

<?php if ($error !== null): ?>
<section class="panel error">
<h2>Erreur OPS controlee</h2>
<p><?= ops_action_e($error) ?></p>
</section>
<?php endif; ?>

<section class="panel">
<h2>Resultat controle</h2>
<pre><?= ops_action_e(ops_action_json($response)) ?></pre>
</section>
</main>
</body>
</html