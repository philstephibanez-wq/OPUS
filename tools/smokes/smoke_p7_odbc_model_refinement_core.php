<?php
declare(strict_types=1);

use Opus\Model\ModelField;
use Opus\Model\ModelFieldProfile;
use Opus\Model\ModelMutationValidator;
use Opus\Model\ModelTableIdentity;
use Opus\Model\ModelWriteProfile;
use Opus\Model\TableModel;
use Opus\OdbcExplorer\Crud\OdbcCrudCommand;
use Opus\OdbcExplorer\Crud\OdbcCrudPredicate;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

require_once $root . '/Opus/Model/ModelMutationIntent.php';
require_once $root . '/Opus/Model/ModelFieldProfile.php';
require_once $root . '/Opus/Model/ModelTableIdentity.php';
require_once $root . '/Opus/Model/ModelMutationValidationReport.php';
require_once $root . '/Opus/Model/ModelMutationValidator.php';
require_once $root . '/Opus/Model/ModelWriteProfile.php';

echo "P7_ODBC_MODEL_REFINEMENT_CORE_SMOKE\n";

$model = new TableModel('users_model', 'users', [
    new ModelField('id', 'integer', false, null, null, null, [
        'primary_key' => true,
        'generated' => true,
        'insertable' => false,
        'updateable' => false,
    ]),
    new ModelField('name', 'string', false, 20, null, null, [
        'required' => true,
        'insertable' => true,
        'updateable' => true,
    ]),
    new ModelField('email', 'string', true, 40, null, null, [
        'insertable' => true,
        'updateable' => true,
    ]),
], [
    'primary_key' => ['id'],
]);

$identity = ModelTableIdentity::fromTableModel($model);
if (!$identity->hasPrimaryKey() || $identity->primaryKeys() !== ['id']) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_IDENTITY=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_IDENTITY=OK\n";

$idProfile = ModelFieldProfile::fromField($model->field('id'));
$nameProfile = ModelFieldProfile::fromField($model->field('name'));
if (!$idProfile->isPrimaryKey() || !$idProfile->isGenerated() || $idProfile->isInsertable() || $idProfile->isUpdateable()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_ID_PROFILE=FAIL');
}
if (!$nameProfile->isRequiredOnInsert() || !$nameProfile->isInsertable() || !$nameProfile->isUpdateable()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_NAME_PROFILE=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_FIELD_PROFILES=OK\n";

$writeProfile = ModelWriteProfile::fromTableModel($model)->toArray();
if (($writeProfile['contract'] ?? '') !== 'OPUS_MODEL_WRITE_PROFILE_V1') {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_WRITE_PROFILE_CONTRACT=FAIL');
}
if ($writeProfile['insertable_fields'] !== ['name', 'email'] || $writeProfile['updateable_fields'] !== ['name', 'email'] || $writeProfile['required_insert_fields'] !== ['name']) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_WRITE_PROFILE_FIELDS=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_WRITE_PROFILE=OK\n";

$validator = new ModelMutationValidator();

$validInsert = $validator->validateInsert($model, ['name' => 'Alice', 'email' => 'alice@example.test']);
if (!$validInsert->isValid()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_INSERT_VALID=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_INSERT_VALID=OK\n";

$insertWithGeneratedId = $validator->validateInsert($model, ['id' => 10, 'name' => 'Alice']);
if ($insertWithGeneratedId->isValid()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_INSERT_GENERATED_REJECTED=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_INSERT_GENERATED_REJECTED=OK\n";

$insertMissingRequired = $validator->validateInsert($model, ['email' => 'alice@example.test']);
if ($insertMissingRequired->isValid()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_INSERT_REQUIRED_REJECTED=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_INSERT_REQUIRED_REJECTED=OK\n";

$insertTooLong = $validator->validateInsert($model, ['name' => str_repeat('x', 21)]);
if ($insertTooLong->isValid()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_INSERT_LENGTH_REJECTED=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_INSERT_LENGTH_REJECTED=OK\n";

$validUpdate = $validator->validateUpdate($model, ['email' => 'new@example.test'], ['id' => 10]);
if (!$validUpdate->isValid()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_UPDATE_VALID=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_UPDATE_VALID=OK\n";

$updateId = $validator->validateUpdate($model, ['id' => 11], ['id' => 10]);
if ($updateId->isValid()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_UPDATE_ID_REJECTED=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_UPDATE_ID_REJECTED=OK\n";

$updateNoPredicate = $validator->validateUpdate($model, ['email' => 'new@example.test'], []);
if ($updateNoPredicate->isValid()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_UPDATE_PREDICATE_REJECTED=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_UPDATE_PREDICATE_REJECTED=OK\n";

$deleteValid = $validator->validateDelete($model, ['id' => 10]);
if (!$deleteValid->isValid()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_DELETE_VALID=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_DELETE_VALID=OK\n";

$deleteUnknownPredicate = $validator->validateDelete($model, ['unknown' => 10]);
if ($deleteUnknownPredicate->isValid()) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_DELETE_UNKNOWN_REJECTED=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_DELETE_UNKNOWN_REJECTED=OK\n";

$validInsert->assertValid();
try {
    $insertTooLong->assertValid();
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_ASSERT_INVALID=FAIL');
} catch (RuntimeException $exception) {
    if (!str_starts_with($exception->getMessage(), 'OPUS_MODEL_MUTATION_INVALID')) {
        throw $exception;
    }
}
echo "CHECK_MODEL_REFINEMENT_ASSERT_INVALID=OK\n";

$command = OdbcCrudCommand::update(
    $model,
    ['email' => 'new@example.test'],
    OdbcCrudPredicate::fromCriteria(['id' => 10]),
    'tester',
    'confirm',
    'request-1'
);
if ($command->tableName() !== 'users' || $command->values() !== ['email' => 'new@example.test']) {
    throw new RuntimeException('CHECK_MODEL_REFINEMENT_CRUD_COMPATIBILITY=FAIL');
}
echo "CHECK_MODEL_REFINEMENT_CRUD_COMPATIBILITY=OK\n";

echo "P7_ODBC_MODEL_REFINEMENT_CORE_SMOKE_OK\n";
