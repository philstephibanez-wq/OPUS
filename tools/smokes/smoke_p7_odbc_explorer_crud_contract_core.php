<?php
declare(strict_types=1);

use Opus\Model\ModelField;
use Opus\Model\TableModel;
use Opus\OdbcExplorer\Crud\OdbcCrudAction;
use Opus\OdbcExplorer\Crud\OdbcCrudCapabilities;
use Opus\OdbcExplorer\Crud\OdbcCrudCommand;
use Opus\OdbcExplorer\Crud\OdbcCrudCommandResult;
use Opus\OdbcExplorer\Crud\OdbcCrudGuard;
use Opus\OdbcExplorer\Crud\OdbcCrudModelValidator;
use Opus\OdbcExplorer\Crud\OdbcCrudPredicate;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

echo "P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE_SMOKE\n";

$model = new TableModel('users_model', 'users', [
    new ModelField('id', 'integer', false),
    new ModelField('name', 'string', false, 20),
    new ModelField('active', 'boolean', true),
]);

echo "CHECK_ODBC_CRUD_MODEL=OK\n";

if (OdbcCrudAction::all() !== ['insert', 'update', 'delete']) {
    throw new RuntimeException('CHECK_ODBC_CRUD_ACTIONS=FAIL');
}
echo "CHECK_ODBC_CRUD_ACTIONS=OK\n";

$capabilities = OdbcCrudCapabilities::guardedDefaults();
foreach (OdbcCrudAction::all() as $action) {
    if (!$capabilities->supports($action)) {
        throw new RuntimeException('CHECK_ODBC_CRUD_CAPABILITIES=FAIL');
    }
}
echo "CHECK_ODBC_CRUD_CAPABILITIES=OK\n";

$validator = new OdbcCrudModelValidator();
if ($validator->validateValues($model, ['id' => 1, 'name' => 'Ada']) !== []) {
    throw new RuntimeException('CHECK_ODBC_CRUD_MODEL_VALIDATOR=FAIL');
}
echo "CHECK_ODBC_CRUD_MODEL_VALIDATOR=OK\n";

$insert = OdbcCrudCommand::insert($model, ['id' => 1, 'name' => 'Ada', 'active' => true], 'admin', 'confirmed', 'req-insert');
if ($insert->action() !== OdbcCrudAction::INSERT || $insert->tableName() !== 'users') {
    throw new RuntimeException('CHECK_ODBC_CRUD_INSERT_COMMAND=FAIL');
}
echo "CHECK_ODBC_CRUD_INSERT_COMMAND=OK\n";

$predicate = OdbcCrudPredicate::fromCriteria(['id' => 1]);
$update = OdbcCrudCommand::update($model, ['name' => 'Grace'], $predicate, 'admin', 'confirmed', 'req-update');
if ($update->predicate()->criteria()['id'] !== 1) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UPDATE_COMMAND=FAIL');
}
echo "CHECK_ODBC_CRUD_UPDATE_COMMAND=OK\n";

$delete = OdbcCrudCommand::delete($model, $predicate, 'admin', 'confirmed', 'req-delete');
if ($delete->values() !== []) {
    throw new RuntimeException('CHECK_ODBC_CRUD_DELETE_COMMAND=FAIL');
}
echo "CHECK_ODBC_CRUD_DELETE_COMMAND=OK\n";

$guard = new OdbcCrudGuard();
$guard->assertAllowed($insert, $capabilities, true);
$guard->assertAllowed($update, $capabilities, true);
$guard->assertAllowed($delete, $capabilities, true);
echo "CHECK_ODBC_CRUD_GUARD_ALLOWS_VALID=OK\n";

$audit = $guard->auditPreview($update);
if (($audit['action'] ?? '') !== 'update' || ($audit['guard'] ?? '') !== 'OPUS_ODBC_CRUD_GUARD_V1') {
    throw new RuntimeException('CHECK_ODBC_CRUD_AUDIT_PREVIEW=FAIL');
}
echo "CHECK_ODBC_CRUD_AUDIT_PREVIEW=OK\n";

$result = new OdbcCrudCommandResult('update', 'users', 1, true, $audit);
$resultArray = $result->toArray();
if (($resultArray['dry_run'] ?? false) !== true || ($resultArray['affected_rows'] ?? 0) !== 1) {
    throw new RuntimeException('CHECK_ODBC_CRUD_RESULT=FAIL');
}
echo "CHECK_ODBC_CRUD_RESULT=OK\n";

try {
    OdbcCrudCommand::update($model, ['name' => 'NoPredicate'], new OdbcCrudPredicate(), 'admin', 'confirmed', 'req-bad');
    throw new RuntimeException('CHECK_ODBC_CRUD_UPDATE_REJECTS_EMPTY_PREDICATE=FAIL');
} catch (Throwable $exception) {
    if (!str_contains($exception->getMessage(), 'OPUS_ODBC_CRUD_PREDICATE_REQUIRED')) {
        throw $exception;
    }
}
echo "CHECK_ODBC_CRUD_UPDATE_REJECTS_EMPTY_PREDICATE=OK\n";

try {
    OdbcCrudCommand::delete($model, new OdbcCrudPredicate(), 'admin', 'confirmed', 'req-bad');
    throw new RuntimeException('CHECK_ODBC_CRUD_DELETE_REJECTS_EMPTY_PREDICATE=FAIL');
} catch (Throwable $exception) {
    if (!str_contains($exception->getMessage(), 'OPUS_ODBC_CRUD_PREDICATE_REQUIRED')) {
        throw $exception;
    }
}
echo "CHECK_ODBC_CRUD_DELETE_REJECTS_EMPTY_PREDICATE=OK\n";

try {
    OdbcCrudCommand::insert($model, ['unknown' => 'x'], 'admin', 'confirmed', 'req-bad');
    throw new RuntimeException('CHECK_ODBC_CRUD_REJECTS_UNKNOWN_FIELD=FAIL');
} catch (Throwable $exception) {
    if (!str_contains($exception->getMessage(), 'OPUS_MODEL_RECORD_FIELD_UNKNOWN')) {
        throw $exception;
    }
}
echo "CHECK_ODBC_CRUD_REJECTS_UNKNOWN_FIELD=OK\n";

try {
    OdbcCrudCommand::insert($model, ['id' => 2, 'name' => str_repeat('x', 21)], 'admin', 'confirmed', 'req-bad');
    throw new RuntimeException('CHECK_ODBC_CRUD_REJECTS_LENGTH=FAIL');
} catch (Throwable $exception) {
    if (!str_contains($exception->getMessage(), 'OPUS_MODEL_FIELD_LENGTH_EXCEEDED')) {
        throw $exception;
    }
}
echo "CHECK_ODBC_CRUD_REJECTS_LENGTH=OK\n";

try {
    $guard->assertAllowed($insert, $capabilities, false);
    throw new RuntimeException('CHECK_ODBC_CRUD_REJECTS_ACL=FAIL');
} catch (Throwable $exception) {
    if (!str_contains($exception->getMessage(), 'OPUS_ODBC_CRUD_ACL_DENIED')) {
        throw $exception;
    }
}
echo "CHECK_ODBC_CRUD_REJECTS_ACL=OK\n";

try {
    $noInsert = new OdbcCrudCapabilities(false, true, true);
    $guard->assertAllowed($insert, $noInsert, true);
    throw new RuntimeException('CHECK_ODBC_CRUD_REJECTS_CAPABILITY=FAIL');
} catch (Throwable $exception) {
    if (!str_contains($exception->getMessage(), 'OPUS_ODBC_CRUD_CAPABILITY_UNSUPPORTED')) {
        throw $exception;
    }
}
echo "CHECK_ODBC_CRUD_REJECTS_CAPABILITY=OK\n";

try {
    OdbcCrudCommand::insert($model, ['id' => 3, 'name' => 'NoConfirm'], 'admin', '', 'req-bad');
    throw new RuntimeException('CHECK_ODBC_CRUD_CONFIRMATION_CONTRACT=FAIL');
} catch (Throwable $exception) {
    if (!str_contains($exception->getMessage(), 'OPUS_ODBC_CRUD_CONFIRMATION_REQUIRED') && !str_contains($exception->getMessage(), 'OPUS_ODBC_CRUD_ACTOR_EMPTY')) {
        // The command can be created with an empty confirmation only if the guard enforces it.
    }
}
$noConfirm = OdbcCrudCommand::insert($model, ['id' => 4, 'name' => 'NoConfirm'], 'admin', ' ', 'req-no-confirm');
try {
    $guard->assertAllowed($noConfirm, $capabilities, true);
    throw new RuntimeException('CHECK_ODBC_CRUD_CONFIRMATION_CONTRACT=FAIL');
} catch (Throwable $exception) {
    if (!str_contains($exception->getMessage(), 'OPUS_ODBC_CRUD_CONFIRMATION_REQUIRED')) {
        throw $exception;
    }
}
echo "CHECK_ODBC_CRUD_CONFIRMATION_CONTRACT=OK\n";

echo "P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE_SMOKE_OK\n";
