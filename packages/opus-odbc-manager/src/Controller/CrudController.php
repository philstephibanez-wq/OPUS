<?php
declare(strict_types=1);

namespace OpusOdbcManager\Controller;

use Opus\Database\Odbc\OdbcDataSourceConfig;
use Opus\Model\ModelField;
use Opus\Model\TableModel;
use Opus\OdbcExplorer\Crud\OdbcCrudAction;
use Opus\OdbcExplorer\Crud\OdbcCrudCapabilities;
use Opus\OdbcExplorer\Crud\OdbcCrudCommand;
use Opus\OdbcExplorer\Crud\OdbcCrudNativePreparedExecutor;
use Opus\OdbcExplorer\Crud\OdbcCrudPredicate;
use Opus\OdbcExplorer\Crud\OdbcCrudService;
use OpusOdbcManager\Diagnostics\OdbcManagerProfiler;
use OpusOdbcManager\View\OdbcManagerCrudViewModelFactory;

/**
 * Guarded CRUD UI controller for the ODBC Manager package.
 *
 * UI routes build structured commands and dry-run previews. They do not accept
 * raw SQL and do not expose DDL.
 */
final class CrudController
{
    private OdbcManagerCrudViewModelFactory $views;
    private OdbcManagerProfiler $profiler;
    private OdbcCrudService $service;
    private string $actorId;
    private string $confirmationToken;

    public function __construct(?OdbcManagerCrudViewModelFactory $views = null, ?OdbcManagerProfiler $profiler = null, ?OdbcCrudService $service = null, string $actorId = 'opus-admin', string $confirmationToken = 'confirmed')
    {
        $this->views = $views ?? new OdbcManagerCrudViewModelFactory();
        $this->profiler = $profiler ?? OdbcManagerProfiler::disabled();
        $this->service = $service ?? $this->dryRunService();
        $this->actorId = trim($actorId) !== '' ? $actorId : 'opus-admin';
        $this->confirmationToken = trim($confirmationToken) !== '' ? $confirmationToken : 'confirmed';
    }

    /** @return array<string,mixed> */
    public function overview(): array
    {
        return $this->profiler->profile('crud_overview', ['controller' => self::class], function (): array {
            return $this->views->overview();
        });
    }

    /** @return array<string,mixed> */
    public function insertForm(string $table = ''): array
    {
        return $this->form(OdbcCrudAction::INSERT, $table, 'crud_insert_form');
    }

    /** @return array<string,mixed> */
    public function updateForm(string $table = ''): array
    {
        return $this->form(OdbcCrudAction::UPDATE, $table, 'crud_update_form');
    }

    /** @return array<string,mixed> */
    public function deleteForm(string $table = ''): array
    {
        return $this->form(OdbcCrudAction::DELETE, $table, 'crud_delete_form');
    }

    /**
     * @param array<string,mixed> $values
     * @param array<string,mixed> $predicate
     * @return array<string,mixed>
     */
    public function dryRun(string $action, string $table = 'users', array $values = [], array $predicate = []): array
    {
        $action = OdbcCrudAction::assertSupported($action);
        $table = $this->safeTableName($table);

        return $this->profiler->profile('crud_dry_run', ['controller' => self::class, 'action' => $action, 'table' => $table], function () use ($action, $table, $values, $predicate): array {
            $model = $this->modelFor($table);
            $command = $this->commandFor($action, $model, $values, $predicate);
            $result = $this->service->dryRun($command, true);
            return $this->views->dryRun($table, $result);
        });
    }

    /** @return array<string,mixed> */
    private function form(string $action, string $table, string $profileAction): array
    {
        $table = $this->safeTableName($table);
        return $this->profiler->profile($profileAction, ['controller' => self::class, 'action' => $action, 'table' => $table], function () use ($action, $table): array {
            return $this->views->form($action, $table);
        });
    }

    /** @param array<string,mixed> $values @param array<string,mixed> $predicate */
    private function commandFor(string $action, TableModel $model, array $values, array $predicate): OdbcCrudCommand
    {
        if ($action === OdbcCrudAction::INSERT) {
            return OdbcCrudCommand::insert($model, $values !== [] ? $values : ['name' => 'Sample'], $this->actorId, $this->confirmationToken, 'crud-ui-dry-run');
        }
        if ($action === OdbcCrudAction::UPDATE) {
            return OdbcCrudCommand::update($model, $values !== [] ? $values : ['name' => 'Updated'], OdbcCrudPredicate::fromCriteria($predicate), $this->actorId, $this->confirmationToken, 'crud-ui-dry-run');
        }

        return OdbcCrudCommand::delete($model, OdbcCrudPredicate::fromCriteria($predicate), $this->actorId, $this->confirmationToken, 'crud-ui-dry-run');
    }

    private function modelFor(string $table): TableModel
    {
        $id = 'crud_ui_' . preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $table);
        return new TableModel($id, $table, [
            new ModelField('id', 'integer', false),
            new ModelField('name', 'string', true, 80),
            new ModelField('email', 'string', true, 120),
        ], ['source' => 'opus-odbc-manager-crud-ui']);
    }

    private function dryRunService(): OdbcCrudService
    {
        $config = OdbcDataSourceConfig::fromArray([
            'id' => 'odbc_manager_ui_dry_run',
            'driver' => 'odbc',
            'dsn' => 'OPUS_ODBC_MANAGER_UI_DRY_RUN',
        ]);

        return new OdbcCrudService(new OdbcCrudNativePreparedExecutor($config), OdbcCrudCapabilities::guardedDefaults());
    }

    private function safeTableName(string $table): string
    {
        $table = trim($table);
        if ($table === '') {
            return 'users';
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $table)) {
            throw new \InvalidArgumentException('OPUS_ODBC_MANAGER_CRUD_UI_TABLE_INVALID: ' . $table);
        }

        return $table;
    }
}
