<?php
declare(strict_types=1);

namespace Opus\Model;

/**
 * Runtime object representation of one row belonging to an OPUS table model.
 */
final class ModelRecord
{
    private TableModel $model;
    /** @var array<string,mixed> */
    private array $values;

    /** @param array<string,mixed> $values */
    public function __construct(TableModel $model, array $values)
    {
        foreach ($values as $fieldName => $value) {
            $field = $model->field((string) $fieldName);
            if ($field === null) {
                throw new \InvalidArgumentException('OPUS_MODEL_RECORD_FIELD_UNKNOWN: ' . $model->id() . ':' . (string) $fieldName);
            }

            $errors = $field->validateValue($value);
            if ($errors !== []) {
                throw new \InvalidArgumentException('OPUS_MODEL_RECORD_FIELD_INVALID: ' . $model->id() . ':' . (string) $fieldName . ':' . implode(',', $errors));
            }
        }

        $this->model = $model;
        $this->values = $values;
    }

    public function model(): TableModel
    {
        return $this->model;
    }

    public function value(string $field): mixed
    {
        if (!$this->model->hasField($field)) {
            throw new \InvalidArgumentException('OPUS_MODEL_RECORD_FIELD_UNKNOWN: ' . $this->model->id() . ':' . $field);
        }

        return $this->values[$field] ?? null;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->values;
    }
}
