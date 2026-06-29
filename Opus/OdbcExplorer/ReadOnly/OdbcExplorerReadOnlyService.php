<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\ReadOnly;

use Opus\Model\TableModel;
use Opus\OdbcExplorer\OdbcExplorerService;

/**
 * Read-only ODBC Explorer orchestration service.
 */
final class OdbcExplorerReadOnlyService
{
    private OdbcExplorerService $explorer;
    private OdbcExplorerReadOnlyCatalogInterface $catalog;

    public function __construct(OdbcExplorerService $explorer, OdbcExplorerReadOnlyCatalogInterface $catalog)
    {
        $this->explorer = $explorer;
        $this->catalog = $catalog;
    }

    /** @return array<string,mixed> */
    public function dataSourceOverview(): array
    {
        $tables = $this->listTables();

        return [
            'datasource' => $this->catalog->dataSourceId(),
            'mode' => 'readonly',
            'table_count' => count($tables),
            'tables' => array_map(
                static fn (OdbcExplorerTableReference $table): array => $table->toArray(),
                $tables
            ),
        ];
    }

    /** @return list<OdbcExplorerTableReference> */
    public function listTables(): array
    {
        return $this->catalog->listTables();
    }

    /** @return array<string,mixed> */
    public function inspectTable(string $modelId, string $table): array
    {
        $reference = $this->requireKnownTable($table);
        $model = $this->explorer->generateTableModel($modelId, $reference->qualifiedName());

        return [
            'datasource' => $this->catalog->dataSourceId(),
            'table' => $reference->toArray(),
            'model' => $model->toArray(),
            'columns' => array_map(
                static fn ($field): array => $field->toArray(),
                $model->fields()
            ),
        ];
    }

    /** @return array<string,mixed> */
    public function previewTable(string $modelId, string $table, int $limit = 20): array
    {
        $reference = $this->requireKnownTable($table);
        $model = $this->explorer->generateTableModel($modelId, $reference->qualifiedName());

        return [
            'datasource' => $this->catalog->dataSourceId(),
            'table' => $reference->toArray(),
            'model' => $model->toArray(),
            'limit' => $limit,
            'rows' => $this->explorer->previewRows($model, $limit),
        ];
    }

    /** @return array<string,mixed> */
    public function prepareLstsarDraftForTable(string $modelId, string $table): array
    {
        $reference = $this->requireKnownTable($table);
        $model = $this->explorer->generateTableModel($modelId, $reference->qualifiedName());

        return $this->explorer->prepareLstsarDraft($model);
    }

    private function requireKnownTable(string $table): OdbcExplorerTableReference
    {
        $table = trim($table);
        if ($table === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_EXPLORER_TABLE_EMPTY');
        }

        foreach ($this->catalog->listTables() as $reference) {
            if ($reference->name() === $table || $reference->qualifiedName() === $table) {
                return $reference;
            }
        }

        throw new \RuntimeException('OPUS_ODBC_EXPLORER_UNKNOWN_TABLE: ' . $table);
    }
}
