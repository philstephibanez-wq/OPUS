<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

/**
 * Result envelope for guarded CRUD execution or dry-run.
 */
final class OdbcCrudCommandResult
{
    private string $action;
    private string $table;
    private int $affectedRows;
    private bool $dryRun;
    /** @var array<string,mixed> */
    private array $audit;

    /** @param array<string,mixed> $audit */
    public function __construct(string $action, string $table, int $affectedRows, bool $dryRun, array $audit = [])
    {
        $this->action = OdbcCrudAction::assertSupported($action);
        $table = trim($table);
        if ($table === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_RESULT_TABLE_EMPTY');
        }
        if ($affectedRows < 0) {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_RESULT_AFFECTED_ROWS_INVALID');
        }

        $this->table = $table;
        $this->affectedRows = $affectedRows;
        $this->dryRun = $dryRun;
        $this->audit = $audit;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'table' => $this->table,
            'affected_rows' => $this->affectedRows,
            'dry_run' => $this->dryRun,
            'audit' => $this->audit,
        ];
    }
}
