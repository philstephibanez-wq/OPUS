<?php
declare(strict_types=1);

use Opus\Database\Odbc\OdbcDataSourceConfig;
use Opus\Model\ModelField;
use Opus\Model\TableModel;
use Opus\OdbcExplorer\Crud\OdbcCrudAction;
use Opus\OdbcExplorer\Crud\OdbcCrudCapabilities;
use Opus\OdbcExplorer\Crud\OdbcCrudCommand;
use Opus\OdbcExplorer\Crud\OdbcCrudNativePreparedExecutor;
use Opus\OdbcExplorer\Crud\OdbcCrudPredicate;
use Opus\OdbcExplorer\Crud\OdbcCrudService;
use Opus\OdbcExplorer\Crud\OdbcCrudSqlBuilder;
use Opus\OdbcExplorer\Crud\OdbcCrudSqlPlan;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

echo "P7_ODBC_EXPLORER_CRUD_CORE_SMOKE\n";

$model = new TableModel('people_model', 'people', [
    new ModelField('id', 'integer', false),
    new ModelField('name', 'string', false, 30),
    new ModelField('email', 'string', true, 80),
]);
$builder = new OdbcCrudSqlBuilder();
$capabilities = OdbcCrudCapabilities::guardedDefaults();

$insert = OdbcCrudCommand::insert($model, ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test'], 'admin', 'CONFIRM', 'REQ-INSERT');
$insertPlan = $builder->build($insert);
if (!$insertPlan instanceof OdbcCrudSqlPlan || $insertPlan->sql() !== 'INSERT INTO people (id, name, email) VALUES (?, ?, ?)') {
    throw new RuntimeException('CHECK_ODBC_CRUD_INSERT_SQL_PLAN=FAIL');
}
if ($insertPlan->parameters() !== [1, 'Ada', 'ada@example.test']) {
    throw new RuntimeException('CHECK_ODBC_CRUD_INSERT_PARAMETERS=FAIL');
}
echo "CHECK_ODBC_CRUD_INSERT_SQL_PLAN=OK\n";
echo "CHECK_ODBC_CRUD_INSERT_PARAMETERS=OK\n";

$update = OdbcCrudCommand::update($model, ['name' => 'Grace'], OdbcCrudPredicate::fromCriteria(['id' => 1]), 'admin', 'CONFIRM', 'REQ-UPDATE');
$updatePlan = $builder->build($update);
if ($updatePlan->sql() !== 'UPDATE people SET name = ? WHERE id = ?') {
    throw new RuntimeException('CHECK_ODBC_CRUD_UPDATE_SQL_PLAN=FAIL');
}
if ($updatePlan->parameters() !== ['Grace', 1]) {
    throw new RuntimeException('CHECK_ODBC_CRUD_UPDATE_PARAMETERS=FAIL');
}
echo "CHECK_ODBC_CRUD_UPDATE_SQL_PLAN=OK\n";
echo "CHECK_ODBC_CRUD_UPDATE_PARAMETERS=OK\n";

$delete = OdbcCrudCommand::delete($model, OdbcCrudPredicate::fromCriteria(['id' => 1, 'email' => null]), 'admin', 'CONFIRM', 'REQ-DELETE');
$deletePlan = $builder->build($delete);
if ($deletePlan->sql() !== 'DELETE FROM people WHERE id = ? AND email IS NULL') {
    throw new RuntimeException('CHECK_ODBC_CRUD_DELETE_SQL_PLAN=FAIL');
}
if ($deletePlan->parameters() !== [1]) {
    throw new RuntimeException('CHECK_ODBC_CRUD_DELETE_PARAMETERS=FAIL');
}
echo "CHECK_ODBC_CRUD_DELETE_SQL_PLAN=OK\n";
echo "CHECK_ODBC_CRUD_DELETE_PARAMETERS=OK\n";

foreach ([$insertPlan, $updatePlan, $deletePlan] as $plan) {
    $sql = $plan->sql();
    if (str_contains($sql, 'Ada') || str_contains($sql, 'Grace') || str_contains($sql, 'ada@example.test')) {
        throw new RuntimeException('CHECK_ODBC_CRUD_NO_VALUE_INTERPOLATION=FAIL');
    }
}
echo "CHECK_ODBC_CRUD_NO_VALUE_INTERPOLATION=OK\n";

$config = OdbcDataSourceConfig::fromArray(['id' => 'crud_dry_run', 'driver' => 'odbc', 'dsn' => 'OPUS_FAKE_DSN']);
$executor = new OdbcCrudNativePreparedExecutor($config);
$service = new OdbcCrudService($executor, $capabilities);
$dryRun = $service->dryRun($update, true)->toArray();
if (($dryRun['dry_run'] ?? null) !== true || ($dryRun['affected_rows'] ?? null) !== 0) {
    throw new RuntimeException('CHECK_ODBC_CRUD_DRY_RUN_RESULT=FAIL');
}
if (($dryRun['audit']['sql_plan']['parameter_count'] ?? null) !== 2 || ($dryRun['audit']['executor'] ?? '') !== 'OPUS_ODBC_CRUD_NATIVE_PREPARED_EXECUTOR_V1') {
    throw new RuntimeException('CHECK_ODBC_CRUD_DRY_RUN_AUDIT=FAIL');
}
echo "CHECK_ODBC_CRUD_DRY_RUN_RESULT=OK\n";
echo "CHECK_ODBC_CRUD_DRY_RUN_AUDIT=OK\n";

try {
    $service->dryRun($update, false);
    throw new RuntimeException('CHECK_ODBC_CRUD_DRY_RUN_ACL_REJECTED=FAIL');
} catch (RuntimeException $e) {
    if (!str_contains($e->getMessage(), 'OPUS_ODBC_CRUD_ACL_DENIED')) {
        throw $e;
    }
}
echo "CHECK_ODBC_CRUD_DRY_RUN_ACL_REJECTED=OK\n";

try {
    $builder->build(OdbcCrudCommand::update($model, ['name' => 'Nope'], OdbcCrudPredicate::fromCriteria([]), 'admin', 'CONFIRM', 'REQ-BAD'));
    throw new RuntimeException('CHECK_ODBC_CRUD_EMPTY_UPDATE_PREDICATE_REJECTED=FAIL');
} catch (RuntimeException $e) {
    if (!str_contains($e->getMessage(), 'OPUS_ODBC_CRUD_PREDICATE_REQUIRED')) {
        throw $e;
    }
}
echo "CHECK_ODBC_CRUD_EMPTY_UPDATE_PREDICATE_REJECTED=OK\n";

if (!in_array(OdbcCrudAction::INSERT, OdbcCrudAction::all(), true) || !in_array(OdbcCrudAction::UPDATE, OdbcCrudAction::all(), true) || !in_array(OdbcCrudAction::DELETE, OdbcCrudAction::all(), true)) {
    throw new RuntimeException('CHECK_ODBC_CRUD_ACTIONS_STILL_AVAILABLE=FAIL');
}
echo "CHECK_ODBC_CRUD_ACTIONS_STILL_AVAILABLE=OK\n";

echo "P7_ODBC_EXPLORER_CRUD_CORE_SMOKE_OK\n";
