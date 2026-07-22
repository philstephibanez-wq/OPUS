<?php
declare(strict_types=1);

namespace Opus\Model;

/**
 * Write/read policy profile derived from ModelField native metadata.
 *
 * This object keeps model semantics outside SQL builders and allows ODBC,
 * LSTSAR and UI layers to consume the same field-level contract.
 */
final class ModelFieldProfile implements ModelFieldProfileInterface
{
    private ModelField $field;
    /** @var array<string,mixed> */
    private array $native;

    private function __construct(ModelField $field)
    {
        $this->field = $field;
        $this->native = $field->native();
    }

    public static function fromField(ModelField $field): self
    {
        return new self($field);
    }

    public function field(): ModelField
    {
        return $this->field;
    }

    public function name(): string
    {
        return $this->field->name();
    }

    public function isPrimaryKey(): bool
    {
        return $this->bool('primary_key') || $this->bool('key') || $this->bool('identity');
    }

    public function isGenerated(): bool
    {
        return $this->bool('generated') || $this->bool('auto_increment') || $this->bool('computed');
    }

    public function isReadOnly(): bool
    {
        return $this->bool('readonly') || $this->bool('read_only') || $this->isGenerated();
    }

    public function isRequiredOnInsert(): bool
    {
        if ($this->isGenerated()) {
            return false;
        }
        if (array_key_exists('required', $this->native)) {
            return $this->bool('required');
        }
        if (array_key_exists('required_on_insert', $this->native)) {
            return $this->bool('required_on_insert');
        }

        return false;
    }

    public function isInsertable(): bool
    {
        if ($this->isReadOnly()) {
            return false;
        }
        if (array_key_exists('insertable', $this->native)) {
            return $this->bool('insertable');
        }

        return true;
    }

    public function isUpdateable(): bool
    {
        if ($this->isReadOnly()) {
            return false;
        }
        if (array_key_exists('updateable', $this->native)) {
            return $this->bool('updateable');
        }
        if (array_key_exists('updatable', $this->native)) {
            return $this->bool('updatable');
        }

        return true;
    }

    public function allowsIntent(string $intent): bool
    {
        $intent = ModelMutationIntent::assertSupported($intent);
        if ($intent === ModelMutationIntent::INSERT) {
            return $this->isInsertable();
        }
        if ($intent === ModelMutationIntent::UPDATE) {
            return $this->isUpdateable();
        }

        return true;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'type' => $this->field->type(),
            'nullable' => $this->field->nullable(),
            'length' => $this->field->length(),
            'precision' => $this->field->precision(),
            'scale' => $this->field->scale(),
            'primary_key' => $this->isPrimaryKey(),
            'generated' => $this->isGenerated(),
            'readonly' => $this->isReadOnly(),
            'insertable' => $this->isInsertable(),
            'updateable' => $this->isUpdateable(),
            'required_on_insert' => $this->isRequiredOnInsert(),
        ];
    }

    private function bool(string $key): bool
    {
        $value = $this->native[$key] ?? false;
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on'], true);
        }

        return false;
    }
}
