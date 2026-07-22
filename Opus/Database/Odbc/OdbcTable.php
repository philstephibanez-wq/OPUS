<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

/**
 * Immutable ODBC table metadata record exposed to OPUS ODBC Explorer.
 */
final class OdbcTable implements OdbcTableInterface
{
    private string $name;
    private string $type;
    private ?string $catalog;
    private ?string $schema;
    private ?string $remarks;

    public function __construct(string $name, string $type = 'TABLE', ?string $catalog = null, ?string $schema = null, ?string $remarks = null)
    {
        $name = trim($name);
        $type = strtoupper(trim($type !== '' ? $type : 'TABLE'));
        if ($name === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_TABLE_NAME_EMPTY');
        }
        if (!in_array($type, ['TABLE', 'VIEW', 'SYSTEM TABLE', 'GLOBAL TEMPORARY', 'LOCAL TEMPORARY', 'ALIAS', 'SYNONYM', 'UNKNOWN'], true)) {
            $type = 'UNKNOWN';
        }

        $this->name = $name;
        $this->type = $type;
        $this->catalog = $catalog !== null && trim($catalog) !== '' ? trim($catalog) : null;
        $this->schema = $schema !== null && trim($schema) !== '' ? trim($schema) : null;
        $this->remarks = $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null;
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
        $parts = [];
        if ($this->schema !== null) {
            $parts[] = $this->schema;
        }
        $parts[] = $this->name;

        return implode('.', $parts);
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
}
