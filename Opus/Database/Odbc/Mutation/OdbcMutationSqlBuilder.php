<?php
declare(strict_types=1);

namespace Opus\Database\Odbc\Mutation;

final class OdbcMutationSqlBuilder
{
    public function build(
        OdbcMutationCommand $command
    ): OdbcMutationSqlPlan {
        return match ($command->action()) {
            OdbcMutationAction::INSERT =>
                $this->insert($command),
            OdbcMutationAction::UPDATE =>
                $this->update($command),
            OdbcMutationAction::DELETE =>
                $this->delete($command),
        };
    }

    private function insert(
        OdbcMutationCommand $command
    ): OdbcMutationSqlPlan {
        $columns = array_keys($command->values());
        $markers = array_fill(0, count($columns), '?');

        return new OdbcMutationSqlPlan(
            $command->action(),
            $command->table(),
            'INSERT INTO ' . $command->table()
                . ' (' . implode(', ', $columns) . ')'
                . ' VALUES (' . implode(', ', $markers) . ')',
            array_values($command->values())
        );
    }

    private function update(
        OdbcMutationCommand $command
    ): OdbcMutationSqlPlan {
        $assignments = [];
        $parameters = [];

        foreach ($command->values() as $column => $value) {
            $assignments[] = $column . ' = ?';
            $parameters[] = $value;
        }

        [$where, $whereParameters] = $this->where(
            $command->predicate()
        );

        return new OdbcMutationSqlPlan(
            $command->action(),
            $command->table(),
            'UPDATE ' . $command->table()
                . ' SET ' . implode(', ', $assignments)
                . ' WHERE ' . $where,
            [...$parameters, ...$whereParameters]
        );
    }

    private function delete(
        OdbcMutationCommand $command
    ): OdbcMutationSqlPlan {
        [$where, $parameters] = $this->where(
            $command->predicate()
        );

        return new OdbcMutationSqlPlan(
            $command->action(),
            $command->table(),
            'DELETE FROM ' . $command->table()
                . ' WHERE ' . $where,
            $parameters
        );
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function where(
        ?OdbcMutationPredicate $predicate
    ): array {
        if (!$predicate instanceof OdbcMutationPredicate) {
            throw new \LogicException(
                'OPUS_ODBC_MUTATION_PREDICATE_REQUIRED'
            );
        }

        $clauses = [];
        $parameters = [];

        foreach ($predicate->conditions() as $column => $value) {
            if ($value === null) {
                $clauses[] = $column . ' IS NULL';
                continue;
            }

            $clauses[] = $column . ' = ?';
            $parameters[] = $value;
        }

        if ($clauses === []) {
            throw new \LogicException(
                'OPUS_ODBC_MUTATION_WHERE_EMPTY'
            );
        }

        return [implode(' AND ', $clauses), $parameters];
    }
}
