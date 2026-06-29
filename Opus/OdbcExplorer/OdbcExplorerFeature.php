<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer;

/**
 * Stable feature identifiers for the OPUS ODBC Explorer.
 *
 * The explorer is the official OPUS administration surface for ODBC-backed
 * databases. It aims for Adminer/phpMyAdmin-style coverage while keeping OPUS
 * contracts, Model generation and LSTSAR preparation at the center.
 */
final class OdbcExplorerFeature
{
    public const LIST_DRIVERS = 'list_drivers';
    public const LIST_DATASOURCES = 'list_datasources';
    public const TEST_CONNECTION = 'test_connection';
    public const LIST_CATALOGS = 'list_catalogs';
    public const LIST_SCHEMAS = 'list_schemas';
    public const LIST_TABLES = 'list_tables';
    public const INSPECT_COLUMNS = 'inspect_columns';
    public const PREVIEW_ROWS = 'preview_rows';
    public const GENERATE_TABLE_MODEL = 'generate_table_model';
    public const GENERATE_LSTSAR_DRAFT = 'generate_lstsar_draft';
    public const RUN_SQL_READONLY = 'run_sql_readonly';
    public const EXPORT_DATA = 'export_data';
    public const IMPORT_DATA = 'import_data';
    public const INSERT_ROW = 'insert_row';
    public const UPDATE_ROW = 'update_row';
    public const DELETE_ROW = 'delete_row';
    public const GENERATE_DDL_DRY_RUN = 'generate_ddl_dry_run';
    public const EXECUTE_DDL_GUARDED = 'execute_ddl_guarded';
    public const MANAGE_INDEXES = 'manage_indexes';
    public const MANAGE_RELATIONS = 'manage_relations';
    public const MANAGE_USERS = 'manage_users';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::LIST_DRIVERS,
            self::LIST_DATASOURCES,
            self::TEST_CONNECTION,
            self::LIST_CATALOGS,
            self::LIST_SCHEMAS,
            self::LIST_TABLES,
            self::INSPECT_COLUMNS,
            self::PREVIEW_ROWS,
            self::GENERATE_TABLE_MODEL,
            self::GENERATE_LSTSAR_DRAFT,
            self::RUN_SQL_READONLY,
            self::EXPORT_DATA,
            self::IMPORT_DATA,
            self::INSERT_ROW,
            self::UPDATE_ROW,
            self::DELETE_ROW,
            self::GENERATE_DDL_DRY_RUN,
            self::EXECUTE_DDL_GUARDED,
            self::MANAGE_INDEXES,
            self::MANAGE_RELATIONS,
            self::MANAGE_USERS,
        ];
    }
}
