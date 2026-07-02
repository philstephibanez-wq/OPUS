<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$body = '<section class="ops-panel"><h2>ODBC Manager</h2><p>ODBC Manager links DSN, driver and connection tests used by source/destination models.</p>';
$body .= '<div class="ops-chain-grid">';
$body .= '<article class="ops-chain-card"><h3>Source DSN</h3><p><code>source_dsn</code></p><p>Driver: <code>odbc</code></p></article>';
$body .= '<article class="ops-chain-card"><h3>Destination DSN</h3><p><code>destination_dsn</code></p><p>Driver: <code>odbc</code></p></article>';
$body .= '<article class="ops-chain-card"><h3>Connection tests</h3><p>Status: explicit check pending</p></article>';
$body .= '</div><p><a class="ops-action-button" href="/opus-lstsar-manager/models">Open Models</a> <a class="ops-action-button" href="/opus-lstsar-manager/diagnostics?profiler=1">Open Logs / Profiler</a></p></section>';

p7ops_render_shell('OPUS OPS ODBC Manager', $body);