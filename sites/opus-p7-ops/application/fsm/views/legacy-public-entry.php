<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$body = '<section class="ops-panel"><h2>FSM</h2><p>FSM controls operation state before CL and LSTSAR execution.</p><p><a class="ops-action-button" href="/opus-lstsar-manager/chain">Back to chain</a></p></section>';
p7ops_render_shell('OPUS OPS FSM', $body);