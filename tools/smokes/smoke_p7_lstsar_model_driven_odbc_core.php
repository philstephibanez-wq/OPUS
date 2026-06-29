<?php
declare(strict_types=1);

use Opus\Lstsar\InMemoryLstsarStore;
use Opus\Lstsar\LstsarConfig;
use Opus\Lstsar\LstsarInMemoryOdbcDestinationWriter;
use Opus\Lstsar\LstsarInMemoryOdbcSourceReader;
use Opus\Lstsar\LstsarModelDrivenOdbcEngine;
use Opus\Lstsar\LstsarNativeOdbcSourceReader;
use Opus\Lstsar\LstsarOdbcCrudDestinationWriter;
use Opus\Lstsar\LstsarStageName;
use Opus\Model\ModelField;
use Opus\Model\TableModel;

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
require_once $root . '/Opus/Lstsar/01_Load.php';
require_once $root . '/Opus/Lstsar/02_Secure.php';
require_once $root . '/Opus/Lstsar/03_Transform.php';
require_once $root . '/Opus/Lstsar/04_Store.php';
require_once $root . '/Opus/Lstsar/05_Archive.php';
require_once $root . '/Opus/Lstsar/06_Report.php';

echo "P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE_SMOKE\n";

$fail = static function (string $check, string $detail = ''): void {
    echo $check . '=FAIL' . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
    exit(1);
};

$config = LstsarConfig::fromArray([
    'contract' => LstsarConfig::CONTRACT,
    'run_id' => 'p7_lstsar_model_driven_odbc_core',
    'source' => ['driver' => 'odbc', 'datasource' => 'legacy_odbc', 'model' => 'legacy_order', 'table' => 'legacy_order'],
    'destination' => ['driver' => 'odbc', 'datasource' => 'opus_odbc', 'model' => 'opus_order', 'table' => 'opus_order'],
    'mapping' => ['legacy_code' => 'code', 'legacy_amount' => 'amount'],
    'security' => ['policy' => 'acl.required', 'acl_granted' => true, 'actor_id' => 'smoke', 'confirmation_token' => 'confirmed'],
    'transform' => [
        'code' => ['trim' => true, 'uppercase' => true, 'pad_right' => ['length' => 4, 'char' => '0']],
        'amount' => ['cast' => 'float', 'round' => 2],
    ],
    'archive' => ['enabled' => true, 'policy' => 'smoke-retention'],
    'report' => ['format' => 'array'],
]);

$sourceModel = new TableModel('legacy_order', 'legacy_order', [
    new ModelField('legacy_code', 'string', false, 8),
    new ModelField('legacy_amount', 'decimal', false, null, 8, 3),
]);
$destinationModel = new TableModel('opus_order', 'opus_order', [
    new ModelField('code', 'string', false, 4),
    new ModelField('amount', 'decimal', false, null, 8, 2),
]);

$sourceReader = new LstsarInMemoryOdbcSourceReader([
    'legacy_order' => ['legacy_code' => ' ab ', 'legacy_amount' => '12.345'],
]);
$destinationWriter = new LstsarInMemoryOdbcDestinationWriter();
$archiveStore = new InMemoryLstsarStore();
$engine = new LstsarModelDrivenOdbcEngine($sourceReader, $destinationWriter, $archiveStore);

if (array_keys($engine->stages()) !== LstsarStageName::all()) {
    $fail('CHECK_LSTSAR_ODBC_ENGINE_STAGES');
}
echo "CHECK_LSTSAR_ODBC_ENGINE_STAGES=OK\n";

$result = $engine->run($config, $sourceModel, $destinationModel);
if (!$result->ok() || $result->destinationRecordId() === null) {
    $fail('CHECK_LSTSAR_ODBC_RUN_OK');
}
echo "CHECK_LSTSAR_ODBC_RUN_OK=OK\n";

$record = $result->transformedRecord();
if (($record['code'] ?? '') !== 'AB00' || ($record['amount'] ?? null) !== 12.35) {
    $fail('CHECK_LSTSAR_ODBC_TRANSFORMED_RECORD', var_export($record, true));
}
echo "CHECK_LSTSAR_ODBC_TRANSFORMED_RECORD=OK\n";

if (!isset($destinationWriter->records()[(string) $result->destinationRecordId()])) {
    $fail('CHECK_LSTSAR_ODBC_DESTINATION_WRITER');
}
echo "CHECK_LSTSAR_ODBC_DESTINATION_WRITER=OK\n";

if ($result->archiveRecordId() === null) {
    $fail('CHECK_LSTSAR_ODBC_ARCHIVE_RECORD');
}
echo "CHECK_LSTSAR_ODBC_ARCHIVE_RECORD=OK\n";

$stageResults = $result->stageResults();
foreach (LstsarStageName::all() as $stage) {
    if (!isset($stageResults[$stage]) || !$stageResults[$stage]->ok()) {
        $fail('CHECK_LSTSAR_ODBC_STAGE_RESULT_' . strtoupper($stage));
    }
}
echo "CHECK_LSTSAR_ODBC_STAGE_RESULTS=OK\n";

$report = $result->report();
if (($report['contract'] ?? '') !== 'OPUS_LSTSAR_MODEL_DRIVEN_ODBC_REPORT_V1' || ($report['ok'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_ODBC_REPORT');
}
echo "CHECK_LSTSAR_ODBC_REPORT=OK\n";

$array = $result->toArray();
if (($array['contract'] ?? '') !== 'OPUS_LSTSAR_MODEL_DRIVEN_ODBC_RUN_RESULT_V1' || !isset($array['stage_results']['load'], $array['stage_results']['report'])) {
    $fail('CHECK_LSTSAR_ODBC_RESULT_ARRAY');
}
echo "CHECK_LSTSAR_ODBC_RESULT_ARRAY=OK\n";

$deniedConfigData = $config->toArray();
$deniedConfigData['security']['acl_granted'] = false;
$denied = $engine->run(LstsarConfig::fromArray($deniedConfigData), $sourceModel, $destinationModel);
if ($denied->ok() !== false || ($denied->violations()[0]->code() ?? '') !== 'OPUS_LSTSAR_SECURIZE_DENIED') {
    $fail('CHECK_LSTSAR_ODBC_SECURIZE_REJECTS');
}
echo "CHECK_LSTSAR_ODBC_SECURIZE_REJECTS=OK\n";

if (!class_exists(LstsarNativeOdbcSourceReader::class) || !class_exists(LstsarOdbcCrudDestinationWriter::class)) {
    $fail('CHECK_LSTSAR_ODBC_NATIVE_BOUNDARIES');
}
echo "CHECK_LSTSAR_ODBC_NATIVE_BOUNDARIES=OK\n";

echo "P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE_SMOKE_OK\n";
