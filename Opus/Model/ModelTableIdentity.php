<?php
declare(strict_types=1);

namespace Opus\Model;

/**
 * Identity/key information for an OPUS TableModel.
 */
final class ModelTableIdentity implements ModelTableIdentityInterface
{
    private TableModel $model;
    /** @var list<string> */
    private array $primaryKeys;

    /** @param list<string> $primaryKeys */
    private function __construct(TableModel $model, array $primaryKeys)
    {
        $normalized = [];
        foreach ($primaryKeys as $field) {
            $field = trim($field);
            if ($field === '' || !$model->hasField($field)) {
                throw new \InvalidArgumentException('OPUS_MODEL_IDENTITY_FIELD_UNKNOWN: ' . $model->id() . ':' . $field);
            }
            $normalized[] = $field;
        }

        $this->model = $model;
        $this->primaryKeys = array_values(array_unique($normalized));
    }

    public static function fromTableModel(TableModel $model): self
    {
        $metadata = $model->metadata();
        $keys = [];

        if (isset($metadata['primary_key']) && is_array($metadata['primary_key'])) {
            foreach ($metadata['primary_key'] as $field) {
                $keys[] = (string) $field;
            }
        }

        if ($keys === []) {
            foreach ($model->fields() as $field) {
                $profile = ModelFieldProfile::fromField($field);
                if ($profile->isPrimaryKey()) {
                    $keys[] = $field->name();
                }
            }
        }

        return new self($model, $keys);
    }

    public function model(): TableModel
    {
        return $this->model;
    }

    public function hasPrimaryKey(): bool
    {
        return $this->primaryKeys !== [];
    }

    /** @return list<string> */
    public function primaryKeys(): array
    {
        return $this->primaryKeys;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'model' => $this->model->id(),
            'table' => $this->model->tableName(),
            'has_primary_key' => $this->hasPrimaryKey(),
            'primary_keys' => $this->primaryKeys,
        ];
    }
}
