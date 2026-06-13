<?php

declare(strict_types=1);

namespace Opus\Lstsa;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaStorePhase belongs to the LSTSA Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LSTSA domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - lstsa-overview
 *   diagrams:
 *     - lstsa-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LSTSAR STORE PHASE
 *
 * @visibility public
 * @role Stores transformed rows through a controlled staging table in the target
 *       database, then commits the final target update only when staging is 100%
 *       valid.
 * @contract The final table is never written directly. A failed staging or final
 *           transaction rolls back and attempts to remove the staging table.
 *           Current implementation is intentionally validated with SQLite and
 *           PDO; unsupported store modes fail explicitly.
 * @sideEffects Writes to the target PDO connection inside a transactional store
 *              workflow and updates counters in the run context.
 */
final class LstsaStorePhase implements LstsaPhaseInterface
{
    public function execute(LstsaPipelineContext $context): void
    {
        $mode = $context->definition->storeMode();
        if (!in_array($mode, ['replace', 'append'], true)) {
            throw new \RuntimeException('Lstsa store mode unsupported for staging execution: ' . $mode);
        }

        $targetTable = $context->definition->storeTable();
        $stageTable = LstsaIdentifier::stageTable((string)$context->run['run_id'], $targetTable);
        $context->stageTable = $stageTable;

        $columns = $this->targetColumns($context);
        $context->targetPdo->exec('DROP TABLE IF EXISTS ' . LstsaIdentifier::quote($stageTable));
        $context->targetPdo->exec($this->createTableSql($stageTable, $columns));

        try {
            $this->insertRows($context, $stageTable, array_keys($columns));
            $this->assertStageCount($context, $stageTable);

            $context->targetPdo->beginTransaction();
            if ($mode === 'replace') {
                $context->targetPdo->exec('DROP TABLE IF EXISTS ' . LstsaIdentifier::quote($targetTable));
                $context->targetPdo->exec($this->createTableSql($targetTable, $columns));
            } else {
                $context->targetPdo->exec($this->createTableSql($targetTable, $columns, true));
            }

            $quotedColumns = implode(', ', array_map([LstsaIdentifier::class, 'quote'], array_keys($columns)));
            $context->targetPdo->exec(
                'INSERT INTO ' . LstsaIdentifier::quote($targetTable) . ' (' . $quotedColumns . ') ' .
                'SELECT ' . $quotedColumns . ' FROM ' . LstsaIdentifier::quote($stageTable)
            );
            $context->targetPdo->exec('DROP TABLE ' . LstsaIdentifier::quote($stageTable));
            $context->targetPdo->commit();
            $context->stageTable = null;
            $context->counts['stored'] = count($context->transformedRows);
        } catch (\Throwable $exception) {
            if ($context->targetPdo->inTransaction()) {
                $context->targetPdo->rollBack();
            }
            $context->targetPdo->exec('DROP TABLE IF EXISTS ' . LstsaIdentifier::quote($stageTable));
            $context->stageTable = null;
            throw $exception;
        }
    }

    /**
     * @return array<string,string>
     */
    private function targetColumns(LstsaPipelineContext $context): array
    {
        $columns = [];
        foreach ($context->definition->mappings() as $mapping) {
            $columns[$mapping->target] = $this->sqlType($mapping->constraint->type);
        }
        return $columns;
    }

    /**
     * @param array<string,string> $columns
     */
    private function createTableSql(string $table, array $columns, bool $ifNotExists = false): string
    {
        $parts = [];
        foreach ($columns as $name => $type) {
            $parts[] = LstsaIdentifier::quote($name) . ' ' . $type;
        }

        return 'CREATE TABLE ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') . LstsaIdentifier::quote($table) . ' (' . implode(', ', $parts) . ')';
    }

    /**
     * @param list<string> $columns
     */
    private function insertRows(LstsaPipelineContext $context, string $table, array $columns): void
    {
        if ($context->transformedRows === []) {
            return;
        }

        $quotedColumns = implode(', ', array_map([LstsaIdentifier::class, 'quote'], $columns));
        $placeholders = implode(', ', array_map(static fn(string $name): string => ':' . $name, $columns));
        $statement = $context->targetPdo->prepare(
            'INSERT INTO ' . LstsaIdentifier::quote($table) . ' (' . $quotedColumns . ') VALUES (' . $placeholders . ')'
        );

        foreach ($context->transformedRows as $row) {
            $params = [];
            foreach ($columns as $column) {
                $params[':' . $column] = $row[$column] ?? null;
            }
            $statement->execute($params);
        }
    }

    private function assertStageCount(LstsaPipelineContext $context, string $stageTable): void
    {
        $count = (int)$context->targetPdo->query('SELECT COUNT(*) FROM ' . LstsaIdentifier::quote($stageTable))->fetchColumn();
        if ($count !== count($context->transformedRows)) {
            throw new \RuntimeException('Lstsa staging row count mismatch');
        }
    }

    private function sqlType(string $type): string
    {
        return match ($type) {
            'bool', 'int', 'integer' => 'INTEGER',
            'float', 'decimal', 'number' => 'REAL',
            default => 'TEXT',
        };
    }
}
