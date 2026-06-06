<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaRunStatus.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaRunStore.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaScheduler.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaRunner.php';

use ASAP\Lstsa\LstsaRunStatus;
use ASAP\Lstsa\LstsaRunStore;
use ASAP\Lstsa\LstsaScheduler;
use ASAP\Lstsa\LstsaRunner;

$store = new LstsaRunStore($root);
$scheduler = new LstsaScheduler($store);
$runner = new LstsaRunner($store);

$scheduled = $scheduler->enqueueSmokeRun();
echo 'P112Q2I2_SCHEDULED_RUN_ID=' . $scheduled['run_id'] . PHP_EOL;
echo 'P112Q2I2_SCHEDULED_STATUS=' . $scheduled['status'] . PHP_EOL;

$finished = $runner->runOnce('p112q2i2_recipe_runner');

if ($finished === null) {
    fwrite(STDERR, 'P112Q2I2 expected one pending run, got none' . PHP_EOL);
    exit(1);
}

echo 'P112Q2I2_RUN_ID=' . $finished['run_id'] . PHP_EOL;
echo 'P112Q2I2_RUN_STATUS=' . $finished['status'] . PHP_EOL;
echo 'P112Q2I2_RUN_REPORT_JSON=' . $finished['report_json'] . PHP_EOL;
echo 'P112Q2I2_RUN_REPORT_MD=' . $finished['report_md'] . PHP_EOL;

if ($finished['status'] !== LstsaRunStatus::DONE) {
    fwrite(STDERR, 'P112Q2I2 expected DONE status' . PHP_EOL);
    exit(1);
}

if (!is_file((string)$finished['report_json'])) {
    fwrite(STDERR, 'P112Q2I2 missing JSON report' . PHP_EOL);
    exit(1);
}

if (!is_file((string)$finished['report_md'])) {
    fwrite(STDERR, 'P112Q2I2 missing MD report' . PHP_EOL);
    exit(1);
}

if (($finished['counts']['loaded'] ?? 0) !== 3) {
    fwrite(STDERR, 'P112Q2I2 invalid loaded count' . PHP_EOL);
    exit(1);
}

if (($finished['counts']['stored'] ?? 0) !== 3) {
    fwrite(STDERR, 'P112Q2I2 invalid stored count' . PHP_EOL);
    exit(1);
}

if (($finished['counts']['archived'] ?? 0) !== 1) {
    fwrite(STDERR, 'P112Q2I2 invalid archived count' . PHP_EOL);
    exit(1);
}

echo 'P112Q2I2_Lstsa_RUNNER_SCHEDULER_BASELINE_RECIPE_OK' . PHP_EOL;
exit(0);
