<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\ReadOnly;

/**
 * Immutable table or view reference exposed by the OPUS ODBC Explorer read-only catalog.
 */
final class OdbcExplorerTableReference
{
    private string $name;
    private string $type;
    private ?string $catalog;
    private ?string $schema;
    private ?string $remarks;

    public function __construct(string $name, string $type = 'TABLE', ?string $catalog = null, ?string $schema = null, ?string $remarks = null)
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_EXPLORER_TABLE_NAME_EMPTY');
        }

        $this->name = $name;
        $this->type = strtoupper(trim($type) !== '' ? trim($type) : 'TABLE');
        $this->catalog = $this->normalizeNullable($catalog);
        $this->schema = $this->normalizeNullable($schema);
        $this->remarks = $this->normalizeNullable($remarks);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function catalog(): ?string
    {
        return $this->catalog;
    }

    public function schema(): ?string
    {
        return $this->schema;
    }

    public function remarks(): ?string
    {
        return $this->remarks;
    }

    public function qualifiedName(): string
    {
        if ($this->schema !== null) {
            return $this->schema . '.' . $this->name;
        }

        return $this->name;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'qualified_name' => $this->qualifiedName(),
            'type' => $this->type,
            'catalog' => $this->catalog,
            'schema' => $this->schema,
            'remarks' => $this->remarks,
        ];
    }

    private function normalizeNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
