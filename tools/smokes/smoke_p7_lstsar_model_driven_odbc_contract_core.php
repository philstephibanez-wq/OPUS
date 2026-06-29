<?php
declare(strict_types=1);

use Opus\Lstsar\ArchiveStage;
use Opus\Lstsar\InMemoryLstsarStore;
use Opus\Lstsar\LoadStage;
use Opus\Lstsar\LstsarBackofficeDeclaration;
use Opus\Lstsar\LstsarConfig;
use Opus\Lstsar\LstsarContext;
use Opus\Lstsar\LstsarEngine;
use Opus\Lstsar\LstsarStageInterface;
use Opus\Lstsar\LstsarStageName;
use Opus\Lstsar\ReportStage;
use Opus\Lstsar\SecurizeStage;
use Opus\Lstsar\StoreStage;
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

echo "P7_LSTSAR_MODEL_DRIVEN_ODBC_CONTRACT_CORE_SMOKE\n";

$fail = static function (string $check, string $detail = ''): void {
    echo $check . '=FAIL' . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
    exit(1);
};

$stages = LstsarStageName::all();
if ($stages !== ['load', 'securize', 'transform', 'store', 'archive', 'report']) {
    $fail('CHECK_LSTSAR_STAGE_NAMES');
}
echo "CHECK_LSTSAR_STAGE_NAMES=OK\n";

$engine = new LstsarEngine(new InMemoryLstsarStore());
$stageClasses = $engine->stageClasses();
$expectedClasses = [
    LstsarStageName::LOAD => LoadStage::class,
    LstsarStageName::SECURIZE => SecurizeStage::class,
    LstsarStageName::TRANSFORM => TransformStage::class,
    LstsarStageName::STORE => StoreStage::class,
    LstsarStageName::ARCHIVE => ArchiveStage::class,
    LstsarStageName::REPORT => ReportStage::class,
];
if ($stageClasses !== $expectedClasses) {
    $fail('CHECK_LSTSAR_ENGINE_STAGE_CATALOG');
}
echo "CHECK_LSTSAR_ENGINE_STAGE_CATALOG=OK\n";

$config = LstsarConfig::fromArray([
    'contract' => LstsarConfig::CONTRACT,
    'run_id' => 'p7_lstsar_model_driven_odbc_contract_core',
    'source' => ['driver' => 'odbc', 'datasource' => 'legacy_odbc', 'model' => 'legacy_customer'],
    'destination' => ['driver' => 'odbc', 'datasource' => 'opus_odbc', 'model' => 'opus_customer'],
    'mapping' => ['legacy_code' => 'code', 'legacy_amount' => 'amount'],
    'security' => ['policy' => 'acl.required', 'acl_granted' => true],
    'transform' => [
        'code' => ['trim' => true, 'uppercase' => true],
        'amount' => ['cast' => 'float', 'round' => 2],
    ],
    'archive' => ['enabled' => true, 'policy' => 'default-retention'],
    'report' => ['format' => 'array'],
]);
if (($config->source()['datasource'] ?? '') !== 'legacy_odbc' || ($config->destination()['datasource'] ?? '') !== 'opus_odbc') {
    $fail('CHECK_LSTSAR_CONFIG_ODBC_ENDPOINTS');
}
echo "CHECK_LSTSAR_CONFIG_ODBC_ENDPOINTS=OK\n";

$sourceModel = new TableModel('legacy_customer', 'legacy_customer', [
    new ModelField('legacy_code', 'string', false, 8),
    new ModelField('legacy_amount', 'decimal', false, null, 8, 2),
]);
$destinationModel = new TableModel('opus_customer', 'opus_customer', [
    new ModelField('code', 'string', false, 8),
    new ModelField('amount', 'decimal', false, null, 8, 2),
]);
$context = new LstsarContext($config, $sourceModel, $destinationModel, ['legacy_code' => ' ab ', 'legacy_amount' => '12.345']);
if (($context->toArray()['source_model'] ?? '') !== 'legacy_customer' || ($context->toArray()['destination_model'] ?? '') !== 'opus_customer') {
    $fail('CHECK_LSTSAR_CONTEXT_MODELS');
}
echo "CHECK_LSTSAR_CONTEXT_MODELS=OK\n";

$instances = [];
foreach ($stageClasses as $stage => $class) {
    $instance = new $class();
    if (!$instance instanceof LstsarStageInterface || $instance->name() !== $stage) {
        $fail('CHECK_LSTSAR_STAGE_INSTANCE', $stage);
    }
    $instances[$stage] = $instance;
}
echo "CHECK_LSTSAR_STAGE_INSTANCES=OK\n";

$load = $instances[LstsarStageName::LOAD]->execute($context);
if (!$load->ok() || ($load->payload()['loaded'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_LOAD_STAGE');
}
$context = $context->withStagePayload(LstsarStageName::LOAD, $load->payload());
echo "CHECK_LSTSAR_LOAD_STAGE=OK\n";

$secure = $instances[LstsarStageName::SECURIZE]->execute($context);
if (!$secure->ok()) {
    $fail('CHECK_LSTSAR_SECURIZE_STAGE');
}
$context = $context->withStagePayload(LstsarStageName::SECURIZE, $secure->payload());
echo "CHECK_LSTSAR_SECURIZE_STAGE=OK\n";

$transform = $instances[LstsarStageName::TRANSFORM]->execute($context);
$record = $transform->payload()['transformed_record'] ?? null;
if (!$transform->ok() || !is_array($record) || ($record['code'] ?? '') !== 'AB' || ($record['amount'] ?? null) !== 12.35) {
    $fail('CHECK_LSTSAR_TRANSFORM_STAGE', var_export($record, true));
}
$context = $context->withStagePayload(LstsarStageName::TRANSFORM, $transform->payload())->withTransformedRecord($record);
echo "CHECK_LSTSAR_TRANSFORM_STAGE=OK\n";

$store = $instances[LstsarStageName::STORE]->execute($context);
if (!$store->ok() || ($store->payload()['store_ready'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_STORE_STAGE');
}
$context = $context->withStagePayload(LstsarStageName::STORE, $store->payload());
echo "CHECK_LSTSAR_STORE_STAGE=OK\n";

$archive = $instances[LstsarStageName::ARCHIVE]->execute($context);
if (!$archive->ok() || ($archive->payload()['enabled'] ?? false) !== true) {
    $fail('CHECK_LSTSAR_ARCHIVE_STAGE');
}
$context = $context->withStagePayload(LstsarStageName::ARCHIVE, $archive->payload());
echo "CHECK_LSTSAR_ARCHIVE_STAGE=OK\n";

$report = $instances[LstsarStageName::REPORT]->execute($context);
if (!$report->ok() || ($report->payload()['stage_count'] ?? 0) !== 6) {
    $fail('CHECK_LSTSAR_REPORT_STAGE');
}
echo "CHECK_LSTSAR_REPORT_STAGE=OK\n";

$denied = LstsarConfig::fromArray($config->toArray() + []);
$deniedData = $denied->toArray();
$deniedData['security']['acl_granted'] = false;
$deniedContext = new LstsarContext(LstsarConfig::fromArray($deniedData), $sourceModel, $destinationModel, ['legacy_code' => 'ab', 'legacy_amount' => '12.34']);
$deniedResult = (new SecurizeStage())->execute($deniedContext);
if ($deniedResult->ok() !== false || ($deniedResult->violations()[0]->code() ?? '') !== 'OPUS_LSTSAR_SECURIZE_DENIED') {
    $fail('CHECK_LSTSAR_SECURIZE_REJECTS');
}
echo "CHECK_LSTSAR_SECURIZE_REJECTS=OK\n";

$backoffice = new LstsarBackofficeDeclaration($config, ['source', 'destination', 'mapping', 'security', 'transform', 'archive', 'report']);
$backofficeArray = $backoffice->toArray();
if (($backofficeArray['contract'] ?? '') !== 'OPUS_LSTSAR_BACKOFFICE_DECLARATION_V1' || count($backofficeArray['editable_sections'] ?? []) !== 7) {
    $fail('CHECK_LSTSAR_BACKOFFICE_DECLARATION');
}
echo "CHECK_LSTSAR_BACKOFFICE_DECLARATION=OK\n";

echo "P7_LSTSAR_MODEL_DRIVEN_ODBC_CONTRACT_CORE_SMOKE_OK\n";
