<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer;

/**
 * Functional contract for the OPUS ODBC Explorer.
 *
 * This contract deliberately maps Adminer/phpMyAdmin-style expectations to OPUS
 * ODBC, Model and LSTSAR responsibilities. It is not a UI yet; it defines the
 * scope that later read-only, CRUD and schema-builder milestones must satisfy.
 */
final class OdbcExplorerContract
{
    public const CONTRACT_ID = 'OPUS_ODBC_EXPLORER_CONTRACT_V1';

    /**
     * @return list<OdbcExplorerCapability>
     */
    public function capabilities(): array
    {
        return [
            new OdbcExplorerCapability(
                OdbcExplorerFeature::LIST_DRIVERS,
                OdbcExplorerCapability::STATUS_DRIVER_DEPENDENT,
                'List available ODBC drivers when the host runtime exposes them.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::LIST_DATASOURCES,
                OdbcExplorerCapability::STATUS_CORE,
                'List configured OPUS ODBC data sources from data-driven configuration.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::TEST_CONNECTION,
                OdbcExplorerCapability::STATUS_CORE,
                'Test an ODBC connection through Opus\\Database\\Odbc only.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::LIST_CATALOGS,
                OdbcExplorerCapability::STATUS_DRIVER_DEPENDENT,
                'List catalogs when the ODBC driver supports catalog metadata.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::LIST_SCHEMAS,
                OdbcExplorerCapability::STATUS_DRIVER_DEPENDENT,
                'List schemas when the ODBC driver supports schema metadata.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::LIST_TABLES,
                OdbcExplorerCapability::STATUS_READONLY,
                'List tables through ODBC metadata in a later read-only milestone.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::INSPECT_COLUMNS,
                OdbcExplorerCapability::STATUS_CORE,
                'Inspect columns and convert them into OPUS Model fields.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::PREVIEW_ROWS,
                OdbcExplorerCapability::STATUS_READONLY,
                'Preview rows through OPUS Model records with a mandatory limit.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::GENERATE_TABLE_MODEL,
                OdbcExplorerCapability::STATUS_CORE,
                'Generate a TableModel from an ODBC table.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::GENERATE_LSTSAR_DRAFT,
                OdbcExplorerCapability::STATUS_CORE,
                'Prepare a data-driven LSTSAR draft from OPUS models.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::RUN_SQL_READONLY,
                OdbcExplorerCapability::STATUS_GUARDED,
                'Run read-only SQL only after explicit validation.',
                ['read_only_only' => true, 'no_mutation_by_default' => true]
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::EXPORT_DATA,
                OdbcExplorerCapability::STATUS_PLANNED,
                'Export table/query results to CSV, JSON or SQL.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::IMPORT_DATA,
                OdbcExplorerCapability::STATUS_GUARDED,
                'Import data only through explicit dry-run and mapping checks.',
                ['dry_run_required' => true]
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::INSERT_ROW,
                OdbcExplorerCapability::STATUS_GUARDED,
                'Insert rows through Model validation and explicit confirmation.',
                ['model_validation_required' => true]
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::UPDATE_ROW,
                OdbcExplorerCapability::STATUS_GUARDED,
                'Update rows through Model validation and explicit confirmation.',
                ['model_validation_required' => true, 'where_clause_required' => true]
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::DELETE_ROW,
                OdbcExplorerCapability::STATUS_GUARDED,
                'Delete rows only with explicit confirmation and non-empty predicate.',
                ['confirmation_required' => true, 'where_clause_required' => true]
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::GENERATE_DDL_DRY_RUN,
                OdbcExplorerCapability::STATUS_GUARDED,
                'Generate DDL from OPUS Model without executing it by default.',
                ['dry_run_required' => true]
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::EXECUTE_DDL_GUARDED,
                OdbcExplorerCapability::STATUS_DRIVER_DEPENDENT,
                'Execute DDL only when supported by the driver and explicitly confirmed.',
                ['dry_run_required' => true, 'confirmation_required' => true]
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::MANAGE_INDEXES,
                OdbcExplorerCapability::STATUS_DRIVER_DEPENDENT,
                'Manage indexes when the target database dialect supports it.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::MANAGE_RELATIONS,
                OdbcExplorerCapability::STATUS_DRIVER_DEPENDENT,
                'Manage relations when the target database dialect supports it.'
            ),
            new OdbcExplorerCapability(
                OdbcExplorerFeature::MANAGE_USERS,
                OdbcExplorerCapability::STATUS_DRIVER_DEPENDENT,
                'Manage users and privileges only for compatible drivers and explicit admin contexts.',
                ['admin_context_required' => true]
            ),
        ];
    }

    public function capability(string $feature): ?OdbcExplorerCapability
    {
        foreach ($this->capabilities() as $capability) {
            if ($capability->feature() === $feature) {
                return $capability;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    public function adminerParityMap(): array
    {
        return [
            'contract' => self::CONTRACT_ID,
            'database_browser' => [
                OdbcExplorerFeature::LIST_DATASOURCES,
                OdbcExplorerFeature::TEST_CONNECTION,
                OdbcExplorerFeature::LIST_CATALOGS,
                OdbcExplorerFeature::LIST_SCHEMAS,
                OdbcExplorerFeature::LIST_TABLES,
                OdbcExplorerFeature::INSPECT_COLUMNS,
                OdbcExplorerFeature::PREVIEW_ROWS,
            ],
            'data_editor' => [
                OdbcExplorerFeature::INSERT_ROW,
                OdbcExplorerFeature::UPDATE_ROW,
                OdbcExplorerFeature::DELETE_ROW,
                OdbcExplorerFeature::IMPORT_DATA,
                OdbcExplorerFeature::EXPORT_DATA,
            ],
            'sql_console' => [
                OdbcExplorerFeature::RUN_SQL_READONLY,
            ],
            'schema_builder' => [
                OdbcExplorerFeature::GENERATE_DDL_DRY_RUN,
                OdbcExplorerFeature::EXECUTE_DDL_GUARDED,
                OdbcExplorerFeature::MANAGE_INDEXES,
                OdbcExplorerFeature::MANAGE_RELATIONS,
            ],
            'opus_integration' => [
                OdbcExplorerFeature::GENERATE_TABLE_MODEL,
                OdbcExplorerFeature::GENERATE_LSTSAR_DRAFT,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT_ID,
            'capabilities' => array_map(
                static fn (OdbcExplorerCapability $capability): array => $capability->toArray(),
                $this->capabilities()
            ),
            'adminer_parity_map' => $this->adminerParityMap(),
        ];
    }
}
