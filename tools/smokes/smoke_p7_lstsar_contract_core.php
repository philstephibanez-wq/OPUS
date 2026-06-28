<?php
declare(strict_types=1);

use Opus\Lstsar\InMemoryLstsarStore;
use Opus\Lstsar\LstsarEngine;
use Opus\Security\Access\AccessDecision;

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';

echo "P7_LSTSAR_CONTRACT_CORE_SMOKE\n";

$fail = static function (string $check, string $detail = ''): void {
    echo $check . '=FAIL' . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
    exit(1);
};

$schema = [
    'contract' => 'OPUS_LSTSAR_SCHEMA_V1',
    'fields' => [
        'code' => [
            'source' => ['type' => 'string', 'min_length' => 2, 'max_length' => 8, 'max_bytes' => 8],
            'transform' => ['trim' => true, 'uppercase' => true, 'pad_right' => ['length' => 4, 'char' => '0']],
            'target' => ['type' => 'string', 'exact_length' => 4, 'max_bytes' => 4],
        ],
        'amount' => [
            'source' => ['type' => 'number', 'min' => 0, 'max' => 9999, 'precision' => 6, 'scale' => 3],
            'transform' => ['cast' => 'float', 'round' => 2],
            'target' => ['type' => 'number', 'min' => 0, 'max' => 9999, 'precision' => 6, 'scale' => 2],
        ],
    ],
];

$store = new InMemoryLstsarStore();
$engine = new LstsarEngine($store);

$denied = $engine->process('orders', $schema, ['code' => 'ab', 'amount' => 12.345], AccessDecision::denied('OPUS_ACL_POLICY_DENIED'));
if ($denied->ok() !== false || ($denied->violations()[0]->code() ?? '') !== 'OPUS_LSTSAR_SECURE_DENIED') {
    $fail('CHECK_SECURE_DENIED');
}
echo "CHECK_SECURE_DENIED=OK\n";

$sourceInvalid = $engine->process('orders', $schema, ['code' => 'A', 'amount' => 12.345], AccessDecision::granted('OPUS_ACL_POLICY_MATCHED'));
if ($sourceInvalid->ok() !== false || ($sourceInvalid->violations()[0]->stage() ?? '') !== 'source') {
    $fail('CHECK_SOURCE_LENGTH_REJECTED');
}
echo "CHECK_SOURCE_LENGTH_REJECTED=OK\n";

$targetInvalid = $engine->process('orders', $schema, ['code' => 'abcdef', 'amount' => 12.345], AccessDecision::granted('OPUS_ACL_POLICY_MATCHED'));
if ($targetInvalid->ok() !== false) {
    $fail('CHECK_TARGET_LENGTH_REJECTED');
}
$hasTarget = false;
foreach ($targetInvalid->violations() as $violation) {
    if ($violation->stage() === 'target' && $violation->code() === 'OPUS_LSTSAR_EXACT_LENGTH_INVALID') {
        $hasTarget = true;
    }
}
if (!$hasTarget) {
    $fail('CHECK_TARGET_LENGTH_REJECTED_CODE');
}
echo "CHECK_TARGET_LENGTH_REJECTED=OK\n";

$stored = $engine->process('orders', $schema, ['code' => 'ab', 'amount' => 12.345], AccessDecision::granted('OPUS_ACL_POLICY_MATCHED'));
if (!$stored->ok() || $stored->recordId() === null) {
    $fail('CHECK_STORE_OK');
}
$record = $stored->record();
if (($record['code'] ?? null) !== 'AB00') {
    $fail('CHECK_TRANSFORM_STRING_TARGET', 'code=' . (string) ($record['code'] ?? ''));
}
if (($record['amount'] ?? null) !== 12.35) {
    $fail('CHECK_TRANSFORM_NUMBER_TARGET', 'amount=' . var_export($record['amount'] ?? null, true));
}
echo "CHECK_TRANSFORM_TARGET=OK\n";
echo "CHECK_STORE_OK=OK\n";

$restored = $engine->restore('orders', (string) $stored->recordId());
if ($restored !== $stored->record()) {
    $fail('CHECK_RESTORE_OK');
}
echo "CHECK_RESTORE_OK=OK\n";

$events = $engine->auditTrail('orders');
$stages = array_values(array_unique(array_map(static fn (array $event): string => (string) ($event['stage'] ?? ''), $events)));
foreach (['load', 'secure', 'source', 'target', 'transform', 'store', 'audit', 'restore'] as $expectedStage) {
    if (!in_array($expectedStage, $stages, true)) {
        $fail('CHECK_AUDIT_TRAIL_STAGE', $expectedStage);
    }
}
echo "CHECK_AUDIT_TRAIL=OK\n";

$asArray = $stored->toArray();
if (($asArray['ok'] ?? null) !== true || !isset($asArray['record_id'], $asArray['record'])) {
    $fail('CHECK_RESULT_ARRAY_CONTRACT');
}
echo "CHECK_RESULT_ARRAY_CONTRACT=OK\n";

echo "P7_LSTSAR_CONTRACT_CORE_SMOKE_OK\n";
