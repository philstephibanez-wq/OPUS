<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'ASAP' . DIRECTORY_SEPARATOR . 'LSTSA' . DIRECTORY_SEPARATOR . 'LstsaRunStatus.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'ASAP' . DIRECTORY_SEPARATOR . 'LSTSA' . DIRECTORY_SEPARATOR . 'LstsaRunStore.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'ASAP' . DIRECTORY_SEPARATOR . 'LSTSA' . DIRECTORY_SEPARATOR . 'LstsaScheduler.php';

use ASAP\LSTSA\LstsaRunStore;
use ASAP\LSTSA\LstsaScheduler;

$action = $argv[1] ?? 'enqueue-smoke';

$store = new LstsaRunStore($root);
$scheduler = new LstsaScheduler($store);

if ($action === 'enqueue-smoke') {
    $run = $scheduler->enqueueSmokeRun();
    echo 'P112Q2I3_SCHEDULED_RUN_ID=' . $run['run_id'] . PHP_EOL;
    echo 'P112Q2I3_SCHEDULED_STATUS=' . $run['status'] . PHP_EOL;
    exit(0);
}

if ($action === 'enqueue-memory-batch-smoke') {
    $run = $scheduler->enqueueMemoryBatchSmokeRun();
    echo 'P112Q2I3_SCHEDULED_RUN_ID=' . $run['run_id'] . PHP_EOL;
    echo 'P112Q2I3_SCHEDULED_STATUS=' . $run['status'] . PHP_EOL;
    exit(0);
}

if ($action === 'list-pending') {
    foreach ($store->listRunsByStatus('PENDING') as $run) {
        echo $run['run_id'] . ' ' . $run['lstsa_id'] . ' ' . $run['status'] . PHP_EOL;
    }
    exit(0);
}

fwrite(STDERR, 'Unknown LSTSA scheduler action: ' . $action . PHP_EOL);
exit(1);
