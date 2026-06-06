<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaException.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaFieldConstraint.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaFieldMapping.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaDefinition.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaConfigLoader.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaRunStatus.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaRunStore.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaBatchExecutor.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaRunner.php';

use ASAP\Lstsa\LstsaRunStore;
use ASAP\Lstsa\LstsaRunner;

$action = $argv[1] ?? 'run-once';
$runnerId = $argv[2] ?? 'lstsa_cli_runner';

if ($action !== 'run-once') {
    fwrite(STDERR, 'Unknown Lstsa runner action: ' . $action . PHP_EOL);
    exit(1);
}

$store = new LstsaRunStore($root);
$runner = new LstsaRunner($store);
$run = $runner->runOnce($runnerId);

if ($run === null) {
    echo 'P112Q2I3_RUNNER_NO_PENDING_RUN' . PHP_EOL;
    exit(0);
}

echo 'P112Q2I3_RUN_ID=' . $run['run_id'] . PHP_EOL;
echo 'P112Q2I3_RUN_STATUS=' . $run['status'] . PHP_EOL;
echo 'P112Q2I3_RUN_REPORT_JSON=' . $run['report_json'] . PHP_EOL;
echo 'P112Q2I3_RUN_REPORT_MD=' . $run['report_md'] . PHP_EOL;
exit(0);
