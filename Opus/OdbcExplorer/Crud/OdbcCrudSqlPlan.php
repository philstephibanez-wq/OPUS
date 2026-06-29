<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

/**
 * Prepared SQL plan generated from a guarded ODBC CRUD command.
 *
 * The plan never embeds user values into SQL. All values are exposed as
 * positional parameters intended for odbc_prepare/odbc_execute.
 */
final class OdbcCrudSqlPlan
{
    private string $action;
    private string $table;
    private string $sql;
    /** @var list<mixed> */
    private array $parameters;
    /** @var array<string,mixed> */
    private array $audit;

    /**
     * @param list<mixed> $parameters
     * @param array<string,mixed> $audit
     */
    public function __construct(string $action, string $table, string $sql, array $parameters, array $audit = [])
    {
        $this->action = OdbcCrudAction::assertSupported($action);
        $table = trim($table);
        $sql = trim($sql);
        if ($table === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_SQL_PLAN_TABLE_EMPTY');
        }
        if ($sql === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_SQL_PLAN_SQL_EMPTY');
        }
        foreach ($parameters as $parameter) {
            if ($parameter !== null && !is_scalar($parameter)) {
                throw new \InvalidArgumentException('OPUS_ODBC_CRUD_SQL_PLAN_PARAMETER_INVALID');
            }
        }

        $this->table = $table;
        $this->sql = $sql;
        $this->parameters = array_values($parameters);
        $this->audit = $audit;
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

    /** @return array<string,mixed> */
    public function audit(): array
    {
        return $this->audit;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'table' => $this->table,
            'sql' => $this->sql,
            'parameter_count' => count($this->parameters),
            'audit' => $this->audit,
        ];
    }
}
