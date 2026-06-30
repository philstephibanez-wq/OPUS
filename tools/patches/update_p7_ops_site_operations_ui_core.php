<?php
declare(strict_types=1);

$root = getcwd();
$file = $root . '/sites/opus-p7-ops/public/index.php';

if (!is_file($file)) {
    fwrite(STDERR, 'OPS_INDEX_NOT_FOUND' . PHP_EOL);
    exit(1);
}

$source = file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, 'OPS_INDEX_READ_FAILED' . PHP_EOL);
    exit(1);
}

$css = <<<'CSS'
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
CSS;

$oldCss = "code{color:#ffdf99}\n</style>";
$newCss = "code{color:#ffdf99}\n" . $css . "\n</style>";
if (strpos($source, '.ops-metrics') === false) {
    if (strpos($source, $oldCss) === false) {
        fwrite(STDERR, 'OPS_CSS_ANCHOR_NOT_FOUND' . PHP_EOL);
        exit(1);
    }
    $source = str_replace($oldCss, $newCss, $source);
}

$old = <<<'PHP'
<?php else: ?>
<section class="panel">
<h2>View model réel</h2>
<pre><?= ops_e($json) ?></pre>
</section>
<?php endif; ?>
PHP;

$new = <<<'PHP'
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
<td><div class="ops-actions"><a href="?site=<?= ops_e($site) ?>&operation=<?= ops_e($operationId) ?>&action=preview">Preview</a><a href="?site=<?= ops_e($site) ?>&operation=<?= ops_e($operationId) ?>&action=dry-run">Dry-run</a><a href="?site=<?= ops_e($site) ?>&operation=<?= ops_e($operationId) ?>&action=audit">Audit</a></div></td>
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
PHP;

if (strpos($source, '<h2>Compteurs OPS</h2>') === false) {
    if (strpos($source, $old) === false) {
        fwrite(STDERR, 'OPS_RAW_JSON_BLOCK_NOT_FOUND' . PHP_EOL);
        exit(1);
    }
    $source = str_replace($old, $new, $source);
}

if (file_put_contents($file, $source) === false) {
    fwrite(STDERR, 'OPS_INDEX_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

echo 'P7_OPS_SITE_OPERATIONS_UI_CORE_UPDATED' . PHP_EOL;
