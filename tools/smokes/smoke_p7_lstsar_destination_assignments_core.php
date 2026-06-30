<?php
declare(strict_types=1);

use Opus\Lstsar\InMemoryLstsarStore;
use Opus\Lstsar\LstsarConfig;
use Opus\Lstsar\LstsarInMemoryOdbcDestinationWriter;
use Opus\Lstsar\LstsarInMemoryOdbcSourceReader;
use Opus\Lstsar\LstsarModelDrivenOdbcEngine;
use Opus\Lstsar\LstsarStageName;
use Opus\Lstsar\LstsarTransformHookContext;
use Opus\Lstsar\LstsarTransformHookInterface;
use Opus\Lstsar\LstsarTransformHookRegistry;
use Opus\Lstsar\TransformStage;
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

echo "P7_LSTSAR_DESTINATION_ASSIGNMENTS_CORE_SMOKE\n";

$fail = static function (string $check, string $detail = ''): void {
    echo $check . '=FAIL' . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
    exit(1);
};

final class P7DestinationAssignmentsLabelHook implements LstsarTransformHookInterface
{
    public function name(): string
    {
        return 'orders.compute_label';
    }

    public function compute(LstsarTransformHookContext $context): mixed
    {
        $destination = $context->destinationRecord();
        $source = $context->sourceRecord();

        return (string) ($destination['code'] ?? 'NO_CODE') . ':' . (string) ($source['legacy_amount'] ?? 'NO_AMOUNT');
    }
}

$config = LstsarConfig::fromArray([
    'contract' => LstsarConfig::CONTRACT,
    'run_id' => 'p7_lstsar_destination_assignments_core',
    'source' => ['driver' => 'odbc', 'datasource' => 'legacy_odbc', 'model' => 'legacy_order', 'table' => 'legacy_order'],
    'destination' => ['driver' => 'odbc', 'datasource' => 'opus_odbc', 'model' => 'opus_order', 'table' => 'opus_order'],
    'mapping' => ['legacy_code' => 'code', 'legacy_amount' => 'amount'],
    'security' => ['policy' => 'acl.required', 'acl_granted' => true, 'actor_id' => 'smoke', 'confirmation_token' => 'confirmed'],
    'transform' => [
        'fields' => [
            'code' => ['trim' => true, 'uppercase' => true, 'pad_right' => ['length' => 4, 'char' => '0']],
            'amount' => ['cast' => 'float', 'round' => 2],
        ],
        'assignments' => [
            'client_id' => ['type' => 'constant', 'value' => 'CLIENT_001'],
            'created_by' => ['type' => 'metadata', 'path' => 'actor_id', 'default' => 'lstsar'],
            'source_hash' => ['type' => 'hash', 'source' => 'source', 'fields' => ['legacy_code', 'legacy_amount'], 'algo' => 'sha256'],
            'label' => ['type' => 'hook', 'hook' => 'orders.compute_label'],
            'batch_key' => [
                'type' => 'concat',
                'separator' => '-',
                'parts' => [
                    ['type' => 'metadata', 'path' => 'site_id'],
                    ['type' => 'destination', 'path' => 'code'],
                ],
            ],
        ],
    ],
    'archive' => ['enabled' => true, 'policy' => 'smoke-retention'],
    'report' => ['format' => 'array'],
    'metadata' => ['actor_id' => 'steve', 'site_id' => 'SITE_A'],
]);

$sourceModel = new TableModel('legacy_order', 'legacy_order', [
    new ModelField('legacy_code', 'string', false, 8),
    new ModelField('legacy_amount', 'decimal', false, null, 8, 3),
]);

$destinationModel = new TableModel('opus_order', 'opus_order', [
    new ModelField('code', 'string', false, 4),
    new ModelField('amount', 'decimal', false, null, 8, 2),
    new ModelField('client_id', 'string', false, 20),
    new ModelField('created_by', 'string', false, 20),
    new ModelField('source_hash', 'string', false, 64),
    new ModelField('label', 'string', false, 40),
    new ModelField('batch_key', 'string', false, 40),
]);

$sourceReader = new LstsarInMemoryOdbcSourceReader([
    'legacy_order' => ['legacy_code' => ' ab ', 'legacy_amount' => '12.345'],
]);
$destinationWriter = new LstsarInMemoryOdbcDestinationWriter();
$archiveStore = new InMemoryLstsarStore();

$stages = LstsarModelDrivenOdbcEngine::defaultStages();
$registry = new LstsarTransformHookRegistry([new P7DestinationAssignmentsLabelHook()]);
$stages[LstsarStageName::TRANSFORM] = new TransformStage($registry);
$engine = new LstsarModelDrivenOdbcEngine($sourceReader, $destinationWriter, $archiveStore, $stages);

$result = $engine->run($config, $sourceModel, $destinationModel);
if (!$result->ok()) {
    $fail('CHECK_LSTSAR_ASSIGNMENTS_RUN_OK');
}
echo "CHECK_LSTSAR_ASSIGNMENTS_RUN_OK=OK\n";

$record = $result->transformedRecord();
if (($record['code'] ?? '') !== 'AB00' || ($record['amount'] ?? null) !== 12.35) {
    $fail('CHECK_LSTSAR_ASSIGNMENTS_MAPPING_COMPAT', var_export($record, true));
}
echo "CHECK_LSTSAR_ASSIGNMENTS_MAPPING_COMPAT=OK\n";

if (($record['client_id'] ?? '') !== 'CLIENT_001' || ($record['created_by'] ?? '') !== 'steve') {
    $fail('CHECK_LSTSAR_ASSIGNMENTS_CONSTANT_METADATA', var_export($record, true));
}
echo "CHECK_LSTSAR_ASSIGNMENTS_CONSTANT_METADATA=OK\n";

if (!isset($record['source_hash']) || !is_string($record['source_hash']) || strlen($record['source_hash']) !== 64) {
    $fail('CHECK_LSTSAR_ASSIGNMENTS_HASH', var_export($record, true));
}
echo "CHECK_LSTSAR_ASSIGNMENTS_HASH=OK\n";

if (($record['label'] ?? '') !== 'AB00:12.345' || ($record['batch_key'] ?? '') !== 'SITE_A-AB00') {
    $fail('CHECK_LSTSAR_ASSIGNMENTS_HOOK_CONCAT', var_export($record, true));
}
echo "CHECK_LSTSAR_ASSIGNMENTS_HOOK_CONCAT=OK\n";

$stagePayload = $result->stageResults()[LstsarStageName::TRANSFORM]->payload();
if (!in_array('client_id', $stagePayload['assigned_fields'] ?? [], true) || !in_array('orders.compute_label', $stagePayload['hook_names'] ?? [], true)) {
    $fail('CHECK_LSTSAR_ASSIGNMENTS_STAGE_PAYLOAD');
}
echo "CHECK_LSTSAR_ASSIGNMENTS_STAGE_PAYLOAD=OK\n";

if ($result->destinationRecordId() === null || !isset($destinationWriter->records()[$result->destinationRecordId()])) {
    $fail('CHECK_LSTSAR_ASSIGNMENTS_DESTINATION_WRITER');
}
echo "CHECK_LSTSAR_ASSIGNMENTS_DESTINATION_WRITER=OK\n";

$legacyConfig = LstsarConfig::fromArray([
    'contract' => LstsarConfig::CONTRACT,
    'run_id' => 'p7_lstsar_destination_assignments_legacy_compat',
    'source' => ['driver' => 'odbc', 'datasource' => 'legacy_odbc', 'model' => 'legacy_order', 'table' => 'legacy_order'],
    'destination' => ['driver' => 'odbc', 'datasource' => 'opus_odbc', 'model' => 'opus_order_legacy', 'table' => 'opus_order_legacy'],
    'mapping' => ['legacy_code' => 'code'],
    'security' => ['acl_granted' => true],
    'transform' => [
        'code' => ['trim' => true, 'uppercase' => true],
    ],
]);
$legacySourceModel = new TableModel('legacy_order', 'legacy_order', [
    new ModelField('legacy_code', 'string', false, 8),
]);
$legacyDestinationModel = new TableModel('opus_order_legacy', 'opus_order_legacy', [
    new ModelField('code', 'string', false, 8),
]);
$legacyEngine = new LstsarModelDrivenOdbcEngine(new LstsarInMemoryOdbcSourceReader([
    'legacy_order' => ['legacy_code' => ' cd '],
]), new LstsarInMemoryOdbcDestinationWriter(), null);
$legacyResult = $legacyEngine->run($legacyConfig, $legacySourceModel, $legacyDestinationModel);
if (($legacyResult->transformedRecord()['code'] ?? '') !== 'CD') {
    $fail('CHECK_LSTSAR_ASSIGNMENTS_LEGACY_TRANSFORM_COMPAT');
}
echo "CHECK_LSTSAR_ASSIGNMENTS_LEGACY_TRANSFORM_COMPAT=OK\n";

$missingHookData = $config->toArray();
$missingHookData['run_id'] = 'p7_lstsar_destination_assignments_missing_hook';
$missingHookData['transform']['assignments']['label'] = ['type' => 'hook', 'hook' => 'orders.missing_hook'];
try {
    $missingHookEngine = new LstsarModelDrivenOdbcEngine($sourceReader, new LstsarInMemoryOdbcDestinationWriter(), null);
    $missingHookEngine->run(LstsarConfig::fromArray($missingHookData), $sourceModel, $destinationModel);
    $fail('CHECK_LSTSAR_ASSIGNMENTS_MISSING_HOOK_REJECTED');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'OPUS_LSTSAR_TRANSFORM_HOOK_MISSING')) {
        $fail('CHECK_LSTSAR_ASSIGNMENTS_MISSING_HOOK_REJECTED', $exception->getMessage());
    }
}
echo "CHECK_LSTSAR_ASSIGNMENTS_MISSING_HOOK_REJECTED=OK\n";

$auditPath = $root . '/DOC/OPUS_LSTSAR_SCRIPT_NECESSITY_AUDIT.md';
$audit = is_file($auditPath) ? (string) file_get_contents($auditPath) : '';
foreach (['Required runtime core', 'Required runtime boundaries', 'Required deterministic adapters', 'Future cleanup recommendation'] as $needle) {
    if (!str_contains($audit, $needle)) {
        $fail('CHECK_LSTSAR_SCRIPT_NECESSITY_AUDIT', $needle);
    }
}
echo "CHECK_LSTSAR_SCRIPT_NECESSITY_AUDIT=OK\n";

echo "P7_LSTSAR_DESTINATION_ASSIGNMENTS_CORE_SMOKE_OK\n";
