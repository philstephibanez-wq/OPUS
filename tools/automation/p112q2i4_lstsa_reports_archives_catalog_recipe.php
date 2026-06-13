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
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaReportCatalog.php';

use ASAP\Lstsa\LstsaReportCatalog;
use ASAP\Lstsa\LstsaRunStatus;
use ASAP\Lstsa\LstsaRunStore;
use ASAP\Lstsa\LstsaRunner;
use ASAP\Lstsa\LstsaScheduler;

function p112q2i4_fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$store = new LstsaRunStore($root);
$scheduler = new LstsaScheduler($store);
$runner = new LstsaRunner($store);

$scheduled = $scheduler->enqueueMemoryBatchSmokeRun();
echo 'P112Q2I4_SCHEDULED_RUN_ID=' . $scheduled['run_id'] . PHP_EOL;
echo 'P112Q2I4_SCHEDULED_STATUS=' . $scheduled['status'] . PHP_EOL;

$finished = $runner->runOnce('p112q2i4_recipe_runner');
if ($finished === null) {
    p112q2i4_fail('P112Q2I4 expected one pending Lstsa run, got none');
}

if ($finished['status'] !== LstsaRunStatus::PARTIAL) {
    p112q2i4_fail('P112Q2I4 expected PARTIAL run status');
}

$catalog = new LstsaReportCatalog($root);
$result = $catalog->writeIndex(25);
$index = $result['index'];

if (!is_file((string)$result['json'])) {
    p112q2i4_fail('P112Q2I4 missing catalog JSON');
}
if (!is_file((string)$result['markdown'])) {
    p112q2i4_fail('P112Q2I4 missing catalog Markdown');
}
if (($index['total_runs'] ?? 0) < 1) {
    p112q2i4_fail('P112Q2I4 catalog contains no runs');
}

$foundRun = null;
foreach (($index['runs'] ?? []) as $run) {
    if (is_array($run) && ($run['run_id'] ?? null) === $finished['run_id']) {
        $foundRun = $run;
        break;
    }
}

if (!is_array($foundRun)) {
    p112q2i4_fail('P112Q2I4 finished run missing from report catalog');
}
if (($foundRun['report_json_exists'] ?? false) !== true || ($foundRun['report_md_exists'] ?? false) !== true) {
    p112q2i4_fail('P112Q2I4 run reports not visible in catalog');
}

$artifactSummary = is_array($foundRun['artifact_summary'] ?? null) ? $foundRun['artifact_summary'] : [];
foreach (['archives', 'quarantine', 'checkpoints'] as $kind) {
    if (!isset($artifactSummary[$kind]) || !is_array($artifactSummary[$kind])) {
        p112q2i4_fail('P112Q2I4 missing artifact summary kind: ' . $kind);
    }
    if (($artifactSummary[$kind]['declared'] ?? 0) < 1) {
        p112q2i4_fail('P112Q2I4 artifact summary declared count invalid for: ' . $kind);
    }
    if (($artifactSummary[$kind]['missing'] ?? 1) !== 0) {
        p112q2i4_fail('P112Q2I4 artifact summary has missing files for: ' . $kind);
    }
}

if (count((array)($foundRun['missing_artifacts'] ?? [])) !== 0) {
    p112q2i4_fail('P112Q2I4 missing artifacts should be empty');
}

echo 'P112Q2I4_RUN_ID=' . $finished['run_id'] . PHP_EOL;
echo 'P112Q2I4_RUN_STATUS=' . $finished['status'] . PHP_EOL;
echo 'P112Q2I4_CATALOG_JSON=' . $result['json'] . PHP_EOL;
echo 'P112Q2I4_CATALOG_MD=' . $result['markdown'] . PHP_EOL;
echo 'P112Q2I4_CATALOG_TOTAL_RUNS=' . (string)$index['total_runs'] . PHP_EOL;
echo 'P112Q2I4_CATALOG_VISIBLE_RUNS=' . count((array)$index['runs']) . PHP_EOL;
echo 'P112Q2I4_Lstsa_REPORTS_ARCHIVES_CATALOG_RECIPE_OK' . PHP_EOL;
exit(0);
