<?php
declare(strict_types=1);

namespace Opus\Model;

/**
 * Central validator for OPUS Model write operations.
 *
 * This validator is intentionally database-driver-neutral. ODBC CRUD and future
 * LSTSAR model-driven storage must rely on this layer instead of duplicating
 * field rules in SQL or UI code.
 */
final class ModelMutationValidator
{
    /** @param array<string,mixed> $values */
    public function validateInsert(TableModel $model, array $values): ModelMutationValidationReport
    {
        $report = $this->validateValues($model, ModelMutationIntent::INSERT, $values);

        foreach ($model->fields() as $field) {
            $profile = ModelFieldProfile::fromField($field);
            if ($profile->isRequiredOnInsert() && !array_key_exists($field->name(), $values)) {
                $report->addError('OPUS_MODEL_REQUIRED_FIELD_MISSING', $field->name());
            }
        }

        return $report;
    }

    /** @param array<string,mixed> $values @param array<string,mixed> $predicate */
    public function validateUpdate(TableModel $model, array $values, array $predicate): ModelMutationValidationReport
    {
        $report = $this->validateValues($model, ModelMutationIntent::UPDATE, $values);
        $this->validatePredicateInto($model, ModelMutationIntent::UPDATE, $predicate, $report);

        return $report;
    }

    /** @param array<string,mixed> $predicate */
    public function validateDelete(TableModel $model, array $predicate): ModelMutationValidationReport
    {
        $report = new ModelMutationValidationReport(ModelMutationIntent::DELETE, $model->id());
        $this->validatePredicateInto($model, ModelMutationIntent::DELETE, $predicate, $report);

        return $report;
    }

    /** @param array<string,mixed> $values */
    private function validateValues(TableModel $model, string $intent, array $values): ModelMutationValidationReport
    {
        $intent = ModelMutationIntent::assertSupported($intent);
        $report = new ModelMutationValidationReport($intent, $model->id());

        if ($values === []) {
            $report->addError('OPUS_MODEL_MUTATION_VALUES_EMPTY');
            return $report;
        }

        foreach ($values as $fieldName => $value) {
            $fieldName = (string) $fieldName;
            $field = $model->field($fieldName);
            if ($field === null) {
                $report->addError('OPUS_MODEL_FIELD_UNKNOWN', $fieldName);
                continue;
            }

            $profile = ModelFieldProfile::fromField($field);
            if (!$profile->allowsIntent($intent)) {
                $report->addError('OPUS_MODEL_FIELD_INTENT_FORBIDDEN', $fieldName);
            }

            foreach ($field->validateValue($value) as $error) {
                $report->addError($error, $fieldName);
            }
        }

        return $report;
    }

    /** @param array<string,mixed> $predicate */
    private function validatePredicateInto(TableModel $model, string $intent, array $predicate, ModelMutationValidationReport $report): void
    {
        if (ModelMutationIntent::requiresPredicate($intent) && $predicate === []) {
            $report->addError('OPUS_MODEL_MUTATION_PREDICATE_REQUIRED');
            return;
        }

        foreach ($predicate as $fieldName => $value) {
            $fieldName = (string) $fieldName;
            $field = $model->field($fieldName);
            if ($field === null) {
                $report->addError('OPUS_MODEL_PREDICATE_FIELD_UNKNOWN', $fieldName);
                continue;
            }

            foreach ($field->validateValue($value) as $error) {
                $report->addError('PREDICATE_' . $error, $fieldName);
            }
        }
    }
}
