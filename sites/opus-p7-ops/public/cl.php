<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$body = '<section class="ops-panel"><h2>CL</h2><p>CL links command/orchestration contracts to FSM, Models and LSTSAR actions.</p><p><a class="ops-action-button" href="/opus-lstsar-manager/command-center">Open Command Center</a></p></section>';
p7ops_render_shell('OPUS OPS CL', $body);