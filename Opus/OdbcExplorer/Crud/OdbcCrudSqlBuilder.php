<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

/**
 * Builds parameterized SQL for ODBC CRUD commands.
 *
 * This builder intentionally supports only structured INSERT/UPDATE/DELETE.
 * It does not accept raw SQL and never interpolates values.
 */
final class OdbcCrudSqlBuilder
{
    public function build(OdbcCrudCommand $command): OdbcCrudSqlPlan
    {
        $action = $command->action();
        if ($action === OdbcCrudAction::INSERT) {
            return $this->buildInsert($command);
        }
        if ($action === OdbcCrudAction::UPDATE) {
            return $this->buildUpdate($command);
        }

        return $this->buildDelete($command);
    }

    private function buildInsert(OdbcCrudCommand $command): OdbcCrudSqlPlan
    {
        $table = $this->assertIdentifierPath($command->tableName(), 'table');
        $values = $command->values();
        if ($values === []) {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_INSERT_VALUES_EMPTY');
        }

        $columns = [];
        $parameters = [];
        foreach ($values as $field => $value) {
            $columns[] = $this->assertIdentifier((string) $field, 'column');
            $parameters[] = $value;
        }

        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        return new OdbcCrudSqlPlan(
            OdbcCrudAction::INSERT,
            $table,
            $sql,
            $parameters,
            $this->audit($command, ['value_fields' => $columns])
        );
    }

    private function buildUpdate(OdbcCrudCommand $command): OdbcCrudSqlPlan
    {
        $table = $this->assertIdentifierPath($command->tableName(), 'table');
        $values = $command->values();
        if ($values === []) {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_UPDATE_VALUES_EMPTY');
        }

        $sets = [];
        $parameters = [];
        foreach ($values as $field => $value) {
            $field = $this->assertIdentifier((string) $field, 'column');
            $sets[] = $field . ' = ?';
            $parameters[] = $value;
        }

        [$whereSql, $whereParameters, $whereFields] = $this->where($command->predicate());
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $whereSql;

        return new OdbcCrudSqlPlan(
            OdbcCrudAction::UPDATE,
            $table,
            $sql,
            array_merge($parameters, $whereParameters),
            $this->audit($command, ['value_fields' => array_keys($values), 'predicate_fields' => $whereFields])
        );
    }

    private function buildDelete(OdbcCrudCommand $command): OdbcCrudSqlPlan
    {
        $table = $this->assertIdentifierPath($command->tableName(), 'table');
        [$whereSql, $whereParameters, $whereFields] = $this->where($command->predicate());
        $sql = 'DELETE FROM ' . $table . ' WHERE ' . $whereSql;

        return new OdbcCrudSqlPlan(
            OdbcCrudAction::DELETE,
            $table,
            $sql,
            $whereParameters,
            $this->audit($command, ['predicate_fields' => $whereFields])
        );
    }

    /** @return array{0:string,1:list<mixed>,2:list<string>} */
    private function where(OdbcCrudPredicate $predicate): array
    {
        $criteria = $predicate->criteria();
        if ($criteria === []) {
            throw new \RuntimeException('OPUS_ODBC_CRUD_WHERE_EMPTY');
        }

        $parts = [];
        $parameters = [];
        $fields = [];
        foreach ($criteria as $field => $value) {
            $field = $this->assertIdentifier((string) $field, 'predicate');
            $fields[] = $field;
            if ($value === null) {
                $parts[] = $field . ' IS NULL';
                continue;
            }
            $parts[] = $field . ' = ?';
            $parameters[] = $value;
        }

        return [implode(' AND ', $parts), $parameters, $fields];
    }

    private function assertIdentifier(string $identifier, string $kind): string
    {
        $identifier = trim($identifier);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_' . strtoupper($kind) . '_IDENTIFIER_INVALID: ' . $identifier);
        }

        return $identifier;
    }

    private function assertIdentifierPath(string $identifier, string $kind): string
    {
        $identifier = trim($identifier);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $identifier)) {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_' . strtoupper($kind) . '_IDENTIFIER_INVALID: ' . $identifier);
        }

        return $identifier;
    }

    /** @param array<string,mixed> $extra @return array<string,mixed> */
    private function audit(OdbcCrudCommand $command, array $extra = []): array
    {
        return $command->auditContext() + $extra + [
            'sql_contract' => 'OPUS_ODBC_CRUD_PREPARED_SQL_V1',
            'raw_sql_allowed' => false,
        ];
    }
}
