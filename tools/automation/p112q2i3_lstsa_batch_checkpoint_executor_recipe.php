<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaException.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaFieldConstraint.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaFieldMapping.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaDefinition.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaConfigLoader.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaRunStatus.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaRunStore.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaScheduler.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaBatchExecutor.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaRunner.php';

use ASAP\Lstsa\LstsaRunStatus;
use ASAP\Lstsa\LstsaRunStore;
use ASAP\Lstsa\LstsaScheduler;
use ASAP\Lstsa\LstsaRunner;

function p112q2i3_fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$store = new LstsaRunStore($root);
$scheduler = new LstsaScheduler($store);
$runner = new LstsaRunner($store);

$scheduled = $scheduler->enqueueMemoryBatchSmokeRun();
echo 'P112Q2I3_SCHEDULED_RUN_ID=' . $scheduled['run_id'] . PHP_EOL;
echo 'P112Q2I3_SCHEDULED_STATUS=' . $scheduled['status'] . PHP_EOL;

$finished = $runner->runOnce('p112q2i3_recipe_runner');

if ($finished === null) {
    p112q2i3_fail('P112Q2I3 expected one pending run, got none');
}

echo 'P112Q2I3_RUN_ID=' . $finished['run_id'] . PHP_EOL;
echo 'P112Q2I3_RUN_STATUS=' . $finished['status'] . PHP_EOL;
echo 'P112Q2I3_RUN_LOADED=' . ($finished['counts']['loaded'] ?? -1) . PHP_EOL;
echo 'P112Q2I3_RUN_ACCEPTED=' . ($finished['counts']['accepted'] ?? -1) . PHP_EOL;
echo 'P112Q2I3_RUN_STORED=' . ($finished['counts']['stored'] ?? -1) . PHP_EOL;
echo 'P112Q2I3_RUN_REJECTED=' . ($finished['counts']['rejected'] ?? -1) . PHP_EOL;
echo 'P112Q2I3_RUN_CHECKPOINTS=' . ($finished['counts']['checkpoints'] ?? -1) . PHP_EOL;
echo 'P112Q2I3_RUN_REPORT_JSON=' . $finished['report_json'] . PHP_EOL;
echo 'P112Q2I3_RUN_REPORT_MD=' . $finished['report_md'] . PHP_EOL;

if ($finished['status'] !== LstsaRunStatus::PARTIAL) {
    p112q2i3_fail('P112Q2I3 expected PARTIAL status');
}

$expectedCounts = [
    'loaded' => 5,
    'accepted' => 4,
    'transformed' => 2,
    'stored' => 2,
    'archived' => 1,
    'checkpoints' => 3,
    'rejected' => 3,
    'errors' => 0,
];

foreach ($expectedCounts as $name => $value) {
    if (($finished['counts'][$name] ?? null) !== $value) {
        p112q2i3_fail('P112Q2I3 invalid count ' . $name . ': expected ' . $value . ', got ' . json_encode($finished['counts'][$name] ?? null));
    }
}

foreach (['report_json', 'report_md'] as $key) {
    if (!is_file((string)$finished[$key])) {
        p112q2i3_fail('P112Q2I3 missing ' . $key);
    }
}

if (count($finished['artifacts']['checkpoints'] ?? []) !== 3) {
    p112q2i3_fail('P112Q2I3 checkpoint artifacts missing');
}

if (count($finished['artifacts']['archives'] ?? []) !== 1) {
    p112q2i3_fail('P112Q2I3 archive artifact missing');
}

if (count($finished['artifacts']['quarantine'] ?? []) !== 1) {
    p112q2i3_fail('P112Q2I3 quarantine artifact missing');
}

foreach (array_merge($finished['artifacts']['checkpoints'], $finished['artifacts']['archives'], $finished['artifacts']['quarantine']) as $path) {
    if (!is_file((string)$path)) {
        p112q2i3_fail('P112Q2I3 artifact file missing: ' . (string)$path);
    }
}

echo 'P112Q2I3_Lstsa_BATCH_CHECKPOINT_EXECUTOR_RECIPE_OK' . PHP_EOL;
exit(0);
