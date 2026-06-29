<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer;

use Opus\Database\Odbc\OdbcConnectionInterface;
use Opus\Model\Adapter\OdbcModelAdapter;
use Opus\Model\ModelRecord;
use Opus\Model\TableModel;

/**
 * Read-oriented service layer for the OPUS ODBC Explorer contract core.
 *
 * Later milestones may add UI, CRUD, SQL console and schema-builder services.
 */
final class OdbcExplorerService
{
    private OdbcConnectionInterface $connection;
    private OdbcModelAdapter $adapter;

    public function __construct(OdbcConnectionInterface $connection, ?OdbcModelAdapter $adapter = null)
    {
        $this->connection = $connection;
        $this->adapter = $adapter ?? new OdbcModelAdapter($connection);
    }

    /**
     * @return array<string,mixed>
     */
    public function testConnection(): array
    {
        $this->connection->connect();

        return [
            'ok' => $this->connection->isConnected(),
            'datasource' => $this->connection->dataSource()->toArray(),
        ];
    }

    public function generateTableModel(string $modelId, string $table): TableModel
    {
        return $this->adapter->tableToModel($modelId, $table);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function previewRows(TableModel $model, int $limit = 20): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('OPUS_ODBC_EXPLORER_PREVIEW_LIMIT_INVALID: ' . $limit);
        }

        return array_map(
            static fn (ModelRecord $record): array => $record->toArray(),
            $this->adapter->readRecords($model, $limit)
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function prepareLstsarDraft(TableModel $source, ?TableModel $target = null): array
    {
        $target ??= $source;

        $mapping = [];
        foreach ($source->fields() as $field) {
            if ($target->hasField($field->name())) {
                $mapping[$field->name()] = $field->name();
            }
        }

        if ($mapping === []) {
            throw new \RuntimeException('OPUS_ODBC_EXPLORER_LSTSAR_MAPPING_EMPTY: ' . $source->id());
        }

        return [
            'contract' => 'OPUS_LSTSAR_ODBC_MODEL_DRAFT_V1',
            'source' => [
                'datasource' => (string) ($source->metadata()['datasource'] ?? ''),
                'table' => $source->tableName(),
                'model' => $source->toArray(),
            ],
            'target' => [
                'datasource' => (string) ($target->metadata()['datasource'] ?? ''),
                'table' => $target->tableName(),
                'model' => $target->toArray(),
            ],
            'mapping' => $mapping,
            'guards' => [
                'secure_via_opus_acl' => true,
                'dry_run_before_store' => true,
                'odbc_only_database_access' => true,
            ],
        ];
    }
}
