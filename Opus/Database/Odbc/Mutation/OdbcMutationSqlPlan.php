<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

final class OdbcMutationSqlPlan
{
    /**
     * @param list<mixed> $parameters
     */
    public function __construct(
        private string $action,
        private string $table,
        private string $sql,
        private array $parameters
    ) {
        if (trim($sql) === '') {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_MUTATION_SQL_EMPTY'
            );
        }
    }

    public function action(): string
    {
        return $this->action;
    }

    public function table(): string
    {
        return $this->table;
    }

    public function sql(): string
    {
        return $this->sql;
    }

    /** @return list<mixed> */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'table' => $this->table,
            'sql' => $this->sql,
            'parameter_count' => count($this->parameters),
        ];
    }
}
