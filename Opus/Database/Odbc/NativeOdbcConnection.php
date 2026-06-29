<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

/**
 * Native PHP ODBC implementation for OPUS database access.
 */
final class NativeOdbcConnection implements OdbcConnectionInterface
{
    private OdbcDataSourceConfig $config;
    /** @var mixed */
    private $connection = null;

    public function __construct(OdbcDataSourceConfig $config)
    {
        $this->config = $config;
    }

    public static function assertExtensionAvailable(): void
    {
        if (!extension_loaded('odbc') || !function_exists('odbc_connect')) {
            throw new \RuntimeException('OPUS_ODBC_EXTENSION_MISSING');
        }
    }

    public function dataSource(): OdbcDataSourceConfig
    {
        return $this->config;
    }

    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        self::assertExtensionAvailable();

        $connection = @odbc_connect(
            $this->config->connectionTarget(),
            $this->config->username() ?? '',
            $this->config->password() ?? ''
        );

        if ($connection === false) {
            throw new \RuntimeException('OPUS_ODBC_CONNECT_FAILED: ' . $this->config->id() . ': ' . (string) @odbc_errormsg());
        }

        $this->connection = $connection;
    }

    public function disconnect(): void
    {
        if ($this->connection !== null) {
            @odbc_close($this->connection);
        }
        $this->connection = null;
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /** @return list<OdbcColumn> */
    public function listColumns(string $table): array
    {
        $this->connect();
        $table = $this->assertSqlIdentifier($table, 'table');

        $columns = $this->listColumnsFromMetadata($table);
        if ($columns !== []) {
            return $columns;
        }

        return $this->listColumnsFromEmptySelect($table);
    }

    /** @return list<array<string,mixed>> */
    public function fetchTable(string $table, int $limit = 0): array
    {
        $this->connect();
        $table = $this->assertSqlIdentifier($table, 'table');
        $result = @odbc_exec($this->connection, 'SELECT * FROM ' . $table);
        if ($result === false) {
            throw new \RuntimeException('OPUS_ODBC_FETCH_TABLE_FAILED: ' . $table . ': ' . (string) @odbc_errormsg($this->connection));
        }

        $rows = [];
        while (($row = @odbc_fetch_array($result)) !== false) {
            $rows[] = $this->normalizeRow($row);
            if ($limit > 0 && count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $row */
    public function insertRow(string $table, array $row): int
    {
        $this->connect();
        $table = $this->assertSqlIdentifier($table, 'table');
        if ($row === []) {
            throw new \InvalidArgumentException('OPUS_ODBC_INSERT_ROW_EMPTY: ' . $table);
        }

        $columns = [];
        $values = [];
        foreach ($row as $column => $value) {
            $columns[] = $this->assertSqlIdentifier((string) $column, 'column');
            $values[] = $value;
        }

        $markers = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . $markers . ')';
        $statement = @odbc_prepare($this->connection, $sql);
        if ($statement === false) {
            throw new \RuntimeException('OPUS_ODBC_INSERT_PREPARE_FAILED: ' . $table . ': ' . (string) @odbc_errormsg($this->connection));
        }

        $ok = @odbc_execute($statement, $values);
        if ($ok !== true) {
            throw new \RuntimeException('OPUS_ODBC_INSERT_EXECUTE_FAILED: ' . $table . ': ' . (string) @odbc_errormsg($this->connection));
        }

        return 1;
    }

    /** @return list<OdbcColumn> */
    private function listColumnsFromMetadata(string $table): array
    {
        $result = @odbc_columns($this->connection, null, null, $table, null);
        if ($result === false) {
            return [];
        }

        $columns = [];
        while (($row = @odbc_fetch_array($result)) !== false) {
            $name = (string) $this->rowValue($row, ['COLUMN_NAME', 'column_name'], '');
            if (trim($name) === '') {
                continue;
            }

            $columns[] = new OdbcColumn(
                $name,
                (string) $this->rowValue($row, ['TYPE_NAME', 'type_name'], 'UNKNOWN'),
                $this->intOrNull($this->rowValue($row, ['DATA_TYPE', 'data_type'], null)),
                $this->intOrNull($this->rowValue($row, ['COLUMN_SIZE', 'column_size'], null)),
                $this->intOrNull($this->rowValue($row, ['DECIMAL_DIGITS', 'decimal_digits'], null)),
                (int) $this->rowValue($row, ['NULLABLE', 'nullable'], 1) !== 0,
                (int) $this->rowValue($row, ['ORDINAL_POSITION', 'ordinal_position'], 0)
            );
        }

        usort($columns, static fn (OdbcColumn $a, OdbcColumn $b): int => $a->ordinal() <=> $b->ordinal());

        return array_values($columns);
    }

    /** @return list<OdbcColumn> */
    private function listColumnsFromEmptySelect(string $table): array
    {
        $result = @odbc_exec($this->connection, 'SELECT * FROM ' . $table . ' WHERE 1=0');
        if ($result === false) {
            throw new \RuntimeException('OPUS_ODBC_TABLE_INTROSPECTION_FAILED: ' . $table . ': ' . (string) @odbc_errormsg($this->connection));
        }

        $count = @odbc_num_fields($result);
        if (!is_int($count) || $count < 1) {
            throw new \RuntimeException('OPUS_ODBC_TABLE_HAS_NO_FIELDS: ' . $table);
        }

        $columns = [];
        for ($i = 1; $i <= $count; $i++) {
            $columns[] = new OdbcColumn(
                (string) @odbc_field_name($result, $i),
                (string) @odbc_field_type($result, $i),
                null,
                $this->intOrNull(@odbc_field_len($result, $i)),
                $this->intOrNull(@odbc_field_scale($result, $i)),
                true,
                $i
            );
        }

        return $columns;
    }

    private function assertSqlIdentifier(string $identifier, string $kind): string
    {
        $identifier = trim($identifier);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $identifier)) {
            throw new \InvalidArgumentException('OPUS_ODBC_' . strtoupper($kind) . '_IDENTIFIER_INVALID: ' . $identifier);
        }

        return $identifier;
    }

    /** @param array<string,mixed> $row */
    private function rowValue(array $row, array $keys, mixed $default): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return $default;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param array<string,mixed> $row */
    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
