<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

/**
 * Immutable result returned by a guarded read-only ODBC query.
 */
final class OdbcQueryResult implements OdbcQueryResultInterface
{
    private string $sql;
    /** @var list<OdbcColumn> */
    private array $columns;
    /** @var list<array<string,mixed>> */
    private array $rows;
    private int $limit;
    private bool $limitReached;

    /**
     * @param list<OdbcColumn> $columns
     * @param list<array<string,mixed>> $rows
     */
    public function __construct(string $sql, array $columns, array $rows, int $limit, bool $limitReached)
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_QUERY_RESULT_SQL_EMPTY');
        }
        if ($limit < 1 || $limit > 10000) {
            throw new \InvalidArgumentException('OPUS_ODBC_QUERY_RESULT_LIMIT_INVALID: ' . $limit);
        }
        foreach ($columns as $column) {
            if (!$column instanceof OdbcColumn) {
                throw new \InvalidArgumentException('OPUS_ODBC_QUERY_RESULT_COLUMN_INVALID');
            }
        }

        $this->sql = $sql;
        $this->columns = array_values($columns);
        $this->rows = array_values($rows);
        $this->limit = $limit;
        $this->limitReached = $limitReached;
    }

    public function sql(): string
    {
        return $this->sql;
    }

    /** @return list<OdbcColumn> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return list<array<string,mixed>> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function limitReached(): bool
    {
        return $this->limitReached;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'sql' => $this->sql,
            'columns' => array_map(static fn (OdbcColumn $column): array => $column->toArray(), $this->columns),
            'rows' => $this->rows,
            'row_count' => count($this->rows),
            'limit' => $this->limit,
            'limit_reached' => $this->limitReached,
        ];
    }
}
