<?php
declare(strict_types=1);

namespace OpusOdbcManager\View;

use Opus\OdbcExplorer\Crud\OdbcCrudAction;
use Opus\OdbcExplorer\Crud\OdbcCrudCommandResult;

/**
 * Builds deterministic guarded CRUD view-models for the ODBC Manager UI.
 */
final class OdbcManagerCrudViewModelFactory
{
    public const MODE = 'guarded_crud';

    /** @return array<string,mixed> */
    public function overview(): array
    {
        return [
            'title' => 'Guarded CRUD',
            'mode' => self::MODE,
            'crud_enabled' => true,
            'raw_sql_allowed' => false,
            'ddl_allowed' => false,
            'dry_run_required_before_execute' => true,
            'confirmation_required' => true,
            'actions' => array_map(fn (string $action): array => $this->actionDescriptor($action), OdbcCrudAction::all()),
            'navigation' => $this->navigation(),
        ];
    }

    /** @return array<string,mixed> */
    public function form(string $action, string $table): array
    {
        $action = OdbcCrudAction::assertSupported($action);
        $table = $this->safeTableName($table);

        return [
            'title' => 'Guarded CRUD form',
            'mode' => self::MODE,
            'action' => $action,
            'table' => $table,
            'permission' => 'opus.odbc_manager.' . $action,
            'method' => 'POST',
            'dry_run_route' => 'opus_odbc_manager_crud_dry_run',
            'raw_sql_allowed' => false,
            'ddl_allowed' => false,
            'confirmation_required' => true,
            'predicate_required' => OdbcCrudAction::isDestructive($action),
            'fields' => $this->defaultFields(),
            'navigation' => $this->navigation(),
        ];
    }

    /** @return array<string,mixed> */
    public function dryRun(string $table, OdbcCrudCommandResult $result): array
    {
        return [
            'title' => 'Guarded CRUD dry run',
            'mode' => self::MODE,
            'table' => $this->safeTableName($table),
            'dry_run' => true,
            'result' => $result->toArray(),
            'raw_sql_allowed' => false,
            'ddl_allowed' => false,
            'confirmation_required' => true,
            'navigation' => $this->navigation(),
        ];
    }

    /** @return array<string,mixed> */
    private function actionDescriptor(string $action): array
    {
        $action = OdbcCrudAction::assertSupported($action);
        return [
            'action' => $action,
            'label' => ucfirst($action),
            'permission' => 'opus.odbc_manager.' . $action,
            'destructive' => OdbcCrudAction::isDestructive($action),
            'confirmation_required' => true,
            'dry_run_required_before_execute' => true,
        ];
    }

    /** @return list<array<string,string>> */
    public function navigation(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'opus_odbc_manager_dashboard', 'path' => '/opus-odbc-manager'],
            ['label' => 'Tables', 'route' => 'opus_odbc_manager_tables', 'path' => '/opus-odbc-manager/tables'],
            ['label' => 'Guarded CRUD', 'route' => 'opus_odbc_manager_crud', 'path' => '/opus-odbc-manager/crud'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function defaultFields(): array
    {
        return [
            ['name' => 'id', 'type' => 'integer', 'nullable' => false, 'predicate' => true],
            ['name' => 'name', 'type' => 'string', 'nullable' => true, 'length' => 80],
            ['name' => 'email', 'type' => 'string', 'nullable' => true, 'length' => 120],
        ];
    }

    private function safeTableName(string $table): string
    {
        $table = trim($table);
        return $table === '' ? '__no_table_selected__' : $table;
    }
}
