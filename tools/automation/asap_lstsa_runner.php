<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'ASAP' . DIRECTORY_SEPARATOR . 'LSTSA' . DIRECTORY_SEPARATOR . 'LstsaRunStatus.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'ASAP' . DIRECTORY_SEPARATOR . 'LSTSA' . DIRECTORY_SEPARATOR . 'LstsaRunStore.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'ASAP' . DIRECTORY_SEPARATOR . 'LSTSA' . DIRECTORY_SEPARATOR . 'LstsaRunner.php';

use ASAP\LSTSA\LstsaRunStore;
use ASAP\LSTSA\LstsaRunner;

$action = $argv[1] ?? 'run-once';
$runnerId = $argv[2] ?? 'lstsa_cli_runner';

if ($action !== 'run-once') {
    fwrite(STDERR, 'Unknown LSTSA runner action: ' . $action . PHP_EOL);
    exit(1);
}

$store = new LstsaRunStore($root);
$runner = new LstsaRunner($store);
$run = $runner->runOnce($runnerId);

if ($run === null) {
    echo 'P112Q2I2_RUNNER_NO_PENDING_RUN' . PHP_EOL;
    exit(0);
}

echo 'P112Q2I2_RUN_ID=' . $run['run_id'] . PHP_EOL;
echo 'P112Q2I2_RUN_STATUS=' . $run['status'] . PHP_EOL;
echo 'P112Q2I2_RUN_REPORT_JSON=' . $run['report_json'] . PHP_EOL;
echo 'P112Q2I2_RUN_REPORT_MD=' . $run['report_md'] . PHP_EOL;
exit(0);
