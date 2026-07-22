<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Database\Odbc\NativeOdbcConnection;
use Opus\Database\Odbc\OdbcDataSourceConfig;
use Opus\Model\ModelField;
use Opus\Model\TableModel;

/**
 * Native PHP ODBC source reader for the Load stage.
 *
 * It reads one record from the configured source table using prepared criteria
 * when criteria are declared. Values are never interpolated into SQL.
 */
final class LstsarNativeOdbcSourceReader implements LstsarOdbcSourceReaderInterface, LstsarNativeOdbcSourceReaderInterface
{
    private OdbcDataSourceConfig $sourceConfig;

    public function __construct(OdbcDataSourceConfig $sourceConfig)
    {
        $this->sourceConfig = $sourceConfig;
    }

    public function load(LstsarConfig $config, TableModel $sourceModel): array
    {
        NativeOdbcConnection::assertExtensionAvailable();
        $source = $config->source();
        $table = $this->assertIdentifierPath((string) ($source['table'] ?? $sourceModel->tableName()), 'table');
        $columns = array_map(fn (ModelField $field): string => $this->assertIdentifier($field->name(), 'column'), $sourceModel->fields());
        if ($columns === []) {
            throw new \RuntimeException('OPUS_LSTSAR_ODBC_SOURCE_COLUMNS_EMPTY: ' . $sourceModel->id());
        }

        [$where, $parameters] = $this->where(isset($source['criteria']) && is_array($source['criteria']) ? $source['criteria'] : []);
        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM ' . $table . ($where !== '' ? ' WHERE ' . $where : '');

        $connection = @odbc_connect(
            $this->sourceConfig->connectionTarget(),
            $this->sourceConfig->username() ?? '',
            $this->sourceConfig->password() ?? ''
        );
        if ($connection === false) {
            throw new \RuntimeException('OPUS_LSTSAR_ODBC_SOURCE_CONNECT_FAILED: ' . $this->sourceConfig->id() . ': ' . (string) @odbc_errormsg());
        }

        try {
            $statement = @odbc_prepare($connection, $sql);
            if ($statement === false) {
                throw new \RuntimeException('OPUS_LSTSAR_ODBC_SOURCE_PREPARE_FAILED: ' . (string) @odbc_errormsg($connection));
            }
            $ok = @odbc_execute($statement, $parameters);
            if ($ok !== true) {
                throw new \RuntimeException('OPUS_LSTSAR_ODBC_SOURCE_EXECUTE_FAILED: ' . (string) @odbc_errormsg($connection));
            }
            $row = @odbc_fetch_array($statement);
            if (!is_array($row)) {
                throw new \RuntimeException('OPUS_LSTSAR_ODBC_SOURCE_ROW_MISSING: ' . $sourceModel->id());
            }

            return array_intersect_key($row, array_flip($columns));
        } finally {
            @odbc_close($connection);
        }
    }

    /** @param array<string,mixed> $criteria @return array{0:string,1:list<mixed>} */
    private function where(array $criteria): array
    {
        $parts = [];
        $parameters = [];
        foreach ($criteria as $field => $value) {
            $field = $this->assertIdentifier((string) $field, 'criteria');
            if ($value === null) {
                $parts[] = $field . ' IS NULL';
                continue;
            }
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('OPUS_LSTSAR_ODBC_SOURCE_CRITERIA_VALUE_INVALID: ' . $field);
            }
            $parts[] = $field . ' = ?';
            $parameters[] = $value;
        }

        return [implode(' AND ', $parts), $parameters];
    }

    private function assertIdentifier(string $identifier, string $kind): string
    {
        $identifier = trim($identifier);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_ODBC_SOURCE_' . strtoupper($kind) . '_IDENTIFIER_INVALID: ' . $identifier);
        }

        return $identifier;
    }

    private function assertIdentifierPath(string $identifier, string $kind): string
    {
        $identifier = trim($identifier);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $identifier)) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_ODBC_SOURCE_' . strtoupper($kind) . '_IDENTIFIER_INVALID: ' . $identifier);
        }

        return $identifier;
    }
}
