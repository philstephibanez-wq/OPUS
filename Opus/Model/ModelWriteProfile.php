<?php
declare(strict_types=1);

namespace Opus\Model;

/**
 * Readable write profile for an OPUS TableModel.
 */
final class ModelWriteProfile implements ModelWriteProfileInterface
{
    private TableModel $model;
    private ModelTableIdentity $identity;
    /** @var list<ModelFieldProfile> */
    private array $fields;

    /** @param list<ModelFieldProfile> $fields */
    private function __construct(TableModel $model, ModelTableIdentity $identity, array $fields)
    {
        $this->model = $model;
        $this->identity = $identity;
        $this->fields = $fields;
    }

    public static function fromTableModel(TableModel $model): self
    {
        $fields = [];
        foreach ($model->fields() as $field) {
            $fields[] = ModelFieldProfile::fromField($field);
        }

        return new self($model, ModelTableIdentity::fromTableModel($model), $fields);
    }

    /** @return list<string> */
    public function insertableFields(): array
    {
        return $this->names(static fn (ModelFieldProfile $profile): bool => $profile->isInsertable());
    }

    /** @return list<string> */
    public function updateableFields(): array
    {
        return $this->names(static fn (ModelFieldProfile $profile): bool => $profile->isUpdateable());
    }

    /** @return list<string> */
    public function requiredInsertFields(): array
    {
        return $this->names(static fn (ModelFieldProfile $profile): bool => $profile->isRequiredOnInsert());
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => 'OPUS_MODEL_WRITE_PROFILE_V1',
            'model' => $this->model->id(),
            'table' => $this->model->tableName(),
            'identity' => $this->identity->toArray(),
            'insertable_fields' => $this->insertableFields(),
            'updateable_fields' => $this->updateableFields(),
            'required_insert_fields' => $this->requiredInsertFields(),
            'fields' => array_map(static fn (ModelFieldProfile $profile): array => $profile->toArray(), $this->fields),
        ];
    }

    /** @param callable(ModelFieldProfile):bool $filter @return list<string> */
    private function names(callable $filter): array
    {
        $names = [];
        foreach ($this->fields as $profile) {
            if ($filter($profile)) {
                $names[] = $profile->name();
            }
        }

        return $names;
    }
}
