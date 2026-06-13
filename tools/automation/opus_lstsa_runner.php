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

require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'DatabaseException.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'DatabaseProvider.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'DatabaseConnectionConfig.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'DatabaseConnectionsConfig.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'DatabaseConfigLoader.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'DatabaseMultiConfigLoader.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'DatabaseDsnFactory.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Database.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'PdoDatabaseConnector.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Fsm' . DIRECTORY_SEPARATOR . 'StateMachineException.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Fsm' . DIRECTORY_SEPARATOR . 'StateActionInterface.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Fsm' . DIRECTORY_SEPARATOR . 'StateMemory.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Fsm' . DIRECTORY_SEPARATOR . 'StateDefinition.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Fsm' . DIRECTORY_SEPARATOR . 'TransitionDefinition.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Fsm' . DIRECTORY_SEPARATOR . 'TransitionResult.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Fsm' . DIRECTORY_SEPARATOR . 'StateMachine.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaFsmState.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaFsmSignal.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaFsmController.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaPipelineContext.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaPhaseInterface.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaIdentifier.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaLoadPhase.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaSecureInputPhase.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaTransformPhase.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaSecureOutputPhase.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaStorePhase.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaArchivePhase.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaReportPhase.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaDatabaseStagingExecutor.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaBatchExecutor.php';
require_once $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Lstsa' . DIRECTORY_SEPARATOR . 'LstsaRunner.php';

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
