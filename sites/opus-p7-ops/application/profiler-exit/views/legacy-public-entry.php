<?php
/** P7_OPS_PROFILER_EXIT_FIX_CORE */
declare(strict_types=1);

require_once __DIR__ . '/language.php';

p7ops_profiler_disable_all_modes();

$next = p7ops_profiler_url_without_profiler((string) ($_GET['next'] ?? '/opus-lstsar-manager'));

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: ' . $next, true, 302);
exit;