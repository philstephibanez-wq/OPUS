<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$body = '<section class="ops-panel"><h2>Models Registry</h2><p>Models expose the database, tables, source model and destination model used by LSTSAR.</p>';
$body .= '<div class="ops-chain-grid">';
$body .= '<article class="ops-chain-card" id="database"><h3>Database</h3><p><code>source_dsn</code> / <code>destination_dsn</code></p></article>';
$body .= '<article class="ops-chain-card"><h3>Tables</h3><p><code>orders_source</code> → <code>orders_destination</code></p></article>';
$body .= '<article class="ops-chain-card"><h3>Source model</h3><p><code>source_orders_model</code></p></article>';
$body .= '<article class="ops-chain-card"><h3>Destination model</h3><p><code>destination_orders_model</code></p></article>';
$body .= '</div><p><a class="ops-action-button" href="/opus-lstsar-manager/odbc-manager">Open ODBC Manager</a> <a class="ops-action-button" href="/opus-lstsar-manager/operations">Open LSTSAR</a></p></section>';

p7ops_render_shell('OPUS OPS Models', $body);