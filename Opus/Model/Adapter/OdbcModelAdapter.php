<?php
declare(strict_types=1);

namespace Opus\Model\Adapter;

use Opus\Database\Odbc\OdbcColumn;
use Opus\Database\Odbc\OdbcConnectionInterface;
use Opus\Model\ModelField;
use Opus\Model\ModelRecord;
use Opus\Model\TableModel;

/**
 * Converts ODBC tables and rows into OPUS models and records.
 */
final class OdbcModelAdapter
{
    private OdbcConnectionInterface $connection;

    public function __construct(OdbcConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    public function tableToModel(string $modelId, string $table): TableModel
    {
        $columns = $this->connection->listColumns($table);
        if ($columns === []) {
            throw new \RuntimeException('OPUS_MODEL_ODBC_TABLE_COLUMNS_EMPTY: ' . $table);
        }

        $fields = [];
        foreach ($columns as $column) {
            if (!$column instanceof OdbcColumn) {
                throw new \RuntimeException('OPUS_MODEL_ODBC_COLUMN_INVALID: ' . $table);
            }
            $fields[] = $this->fieldFromColumn($column);
        }

        return new TableModel($modelId, $table, $fields, [
            'source' => 'odbc',
            'datasource' => $this->connection->dataSource()->id(),
        ]);
    }

    /** @return list<ModelRecord> */
    public function readRecords(TableModel $model, int $limit = 0): array
    {
        $rows = $this->connection->fetchTable($model->tableName(), $limit);
        $records = [];
        foreach ($rows as $row) {
            $records[] = new ModelRecord($model, $this->normalizeRowForModel($model, $row));
        }

        return $records;
    }

    /**
     * @param list<ModelRecord|array<string,mixed>> $records
     */
    public function writeRecords(TableModel $model, array $records): int
    {
        $written = 0;
        foreach ($records as $record) {
            $row = $record instanceof ModelRecord ? $record->toArray() : $record;
            $checked = new ModelRecord($model, $this->normalizeRowForModel($model, $row));
            $written += $this->connection->insertRow($model->tableName(), $checked->toArray());
        }

        return $written;
    }

    private function fieldFromColumn(OdbcColumn $column): ModelField
    {
        return new ModelField(
            $column->name(),
            $this->modelTypeFromNativeType($column->nativeType()),
            $column->nullable(),
            $this->lengthForModel($column),
            $column->length(),
            $column->scale(),
            ['odbc' => $column->toArray()]
        );
    }

    private function modelTypeFromNativeType(string $nativeType): string
    {
        $type = strtoupper($nativeType);
        if (preg_match('/INT|LONG|SHORT|BYTE/', $type) === 1) {
            return 'integer';
        }
        if (preg_match('/DEC|NUM|MONEY|CURRENCY/', $type) === 1) {
            return 'decimal';
        }
        if (preg_match('/REAL|FLOAT|DOUBLE/', $type) === 1) {
            return 'float';
        }
        if (preg_match('/BIT|BOOL/', $type) === 1) {
            return 'boolean';
        }
        if (preg_match('/DATE$/', $type) === 1) {
            return 'date';
        }
        if (preg_match('/TIME|STAMP/', $type) === 1) {
            return 'datetime';
        }
        if (preg_match('/BIN|IMAGE|BLOB/', $type) === 1) {
            return 'binary';
        }
        if (preg_match('/TEXT|MEMO|CLOB/', $type) === 1) {
            return 'text';
        }
        if (preg_match('/CHAR|VARCHAR|STRING/', $type) === 1) {
            return 'string';
        }

        return 'unknown';
    }

    private function lengthForModel(OdbcColumn $column): ?int
    {
        $modelType = $this->modelTypeFromNativeType($column->nativeType());
        if ($modelType === 'string' || $modelType === 'binary') {
            return $column->length();
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeRowForModel(TableModel $model, array $row): array
    {
        $normalized = [];
        foreach ($model->fields() as $field) {
            $name = $field->name();
            if (array_key_exists($name, $row)) {
                $normalized[$name] = $row[$name];
                continue;
            }

            $upper = strtoupper($name);
            if (array_key_exists($upper, $row)) {
                $normalized[$name] = $row[$upper];
            }
        }

        return $normalized;
    }
}
