<?php
declare(strict_types=1);

namespace Opus\Model;

/**
 * OPUS representation of a database table or tabular dataset.
 */
final class TableModel
{
    private string $id;
    private string $tableName;
    /** @var array<string,ModelField> */
    private array $fields;
    /** @var array<string,mixed> */
    private array $metadata;

    /**
     * @param list<ModelField> $fields
     * @param array<string,mixed> $metadata
     */
    public function __construct(string $id, string $tableName, array $fields, array $metadata = [])
    {
        $id = trim($id);
        $tableName = trim($tableName);
        if (!preg_match('/^[a-zA-Z0-9_\-.]{1,120}$/', $id)) {
            throw new \InvalidArgumentException('OPUS_MODEL_TABLE_ID_INVALID: ' . $id);
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $tableName)) {
            throw new \InvalidArgumentException('OPUS_MODEL_TABLE_NAME_INVALID: ' . $tableName);
        }
        if ($fields === []) {
            throw new \InvalidArgumentException('OPUS_MODEL_TABLE_FIELDS_EMPTY: ' . $id);
        }

        $indexed = [];
        foreach ($fields as $field) {
            if (!$field instanceof ModelField) {
                throw new \InvalidArgumentException('OPUS_MODEL_TABLE_FIELD_INVALID: ' . $id);
            }
            if (isset($indexed[$field->name()])) {
                throw new \InvalidArgumentException('OPUS_MODEL_TABLE_FIELD_DUPLICATE: ' . $field->name());
            }
            $indexed[$field->name()] = $field;
        }

        $this->id = $id;
        $this->tableName = $tableName;
        $this->fields = $indexed;
        $this->metadata = $metadata;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function tableName(): string
    {
        return $this->tableName;
    }

    /** @return list<ModelField> */
    public function fields(): array
    {
        return array_values($this->fields);
    }

    public function field(string $name): ?ModelField
    {
        return $this->fields[$name] ?? null;
    }

    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    /** @return array<string,mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'table' => $this->tableName,
            'fields' => array_map(static fn (ModelField $field): array => $field->toArray(), $this->fields()),
            'metadata' => $this->metadata,
        ];
    }
}
