<?php
declare(strict_types=1);

namespace Opus\Database;

use Opus\Database\Odbc\Mutation\OdbcMutationCapabilities;
use Opus\Database\Odbc\Mutation\OdbcMutationService;
use Opus\Database\Odbc\Mutation\OdbcNativeMutationExecutor;
use Opus\Database\Odbc\NativeOdbcConnection;
use Opus\Database\Odbc\OdbcConnectionInterface;
use Opus\Database\Odbc\OdbcDataSourceConfig;
use Opus\Database\Odbc\OdbcPreparedConnectionInterface;
use Opus\Model\Adapter\OdbcModelAdapter;
use Opus\Model\ModelRecord;
use Opus\Model\TableModel;

final class Odbc
    implements OdbcInterface
{
    public const CONTRACT = 'OPUS_DATABASE_ODBC_FACADE_V1';

    private OdbcConnectionInterface $connection;
    private OdbcModelAdapter $models;

    public function __construct(OdbcConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->models = new OdbcModelAdapter($connection);
    }

    /**
     * @param array<string,mixed> $configuration
     */
    public static function fromArray(array $configuration): self
    {
        return self::fromConfig(
            OdbcDataSourceConfig::fromArray($configuration)
        );
    }

    public static function fromConfig(
        OdbcDataSourceConfig $configuration
    ): self {
        return new self(
            new NativeOdbcConnection($configuration)
        );
    }

    public static function fromConnection(
        OdbcConnectionInterface $connection
    ): self {
        return new self($connection);
    }

    public function connection(): OdbcConnectionInterface
    {
        return $this->connection;
    }

    public function dataSource(): OdbcDataSourceConfig
    {
        return $this->connection->dataSource();
    }

    /**
     * @return array<string,mixed>
     */
    public function testConnection(): array
    {
        $this->connection->connect();

        return [
            'contract' => self::CONTRACT,
            'ok' => $this->connection->isConnected(),
            'datasource' => $this->dataSource()->toArray(),
        ];
    }

    public function disconnect(): void
    {
        $this->connection->disconnect();
    }

    public function models(): OdbcModelAdapter
    {
        return $this->models;
    }

    public function tableModel(
        string $modelId,
        string $table
    ): TableModel {
        return $this->models->tableToModel($modelId, $table);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function previewRows(
        TableModel $model,
        int $limit = 20
    ): array {
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_PREVIEW_LIMIT_INVALID: ' . $limit
            );
        }

        return array_map(
            static fn (ModelRecord $record): array =>
                $record->toArray(),
            $this->models->readRecords($model, $limit)
        );
    }

    /**
     * @param list<ModelRecord|array<string,mixed>> $records
     */
    public function writeRecords(
        TableModel $model,
        array $records
    ): int {
        return $this->models->writeRecords($model, $records);
    }

    /**
     * @return array<string,mixed>
     */
    public function prepareLstsarDraft(
        TableModel $source,
        ?TableModel $target = null
    ): array {
        $target ??= $source;
        $mapping = [];

        foreach ($source->fields() as $field) {
            if ($target->hasField($field->name())) {
                $mapping[$field->name()] = $field->name();
            }
        }

        if ($mapping === []) {
            throw new \RuntimeException(
                'OPUS_ODBC_LSTSAR_MAPPING_EMPTY: ' . $source->id()
            );
        }

        return [
            'contract' => 'OPUS_LSTSAR_ODBC_MODEL_DRAFT_V1',
            'source' => [
                'datasource' => (string) (
                    $source->metadata()['datasource'] ?? ''
                ),
                'table' => $source->tableName(),
                'model' => $source->toArray(),
            ],
            'target' => [
                'datasource' => (string) (
                    $target->metadata()['datasource'] ?? ''
                ),
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

    public function mutations(
        ?OdbcMutationCapabilities $capabilities = null,
        string $confirmationToken = 'confirmed'
    ): OdbcMutationService {
        if (
            !$this->connection
                instanceof OdbcPreparedConnectionInterface
        ) {
            throw new \LogicException(
                'OPUS_ODBC_PREPARED_CONNECTION_REQUIRED'
            );
        }

        return new OdbcMutationService(
            new OdbcNativeMutationExecutor($this->connection),
            $capabilities ?? OdbcMutationCapabilities::all(),
            $confirmationToken
        );
    }
}
