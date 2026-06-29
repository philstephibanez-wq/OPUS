<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

use Opus\Database\Odbc\NativeOdbcConnection;
use Opus\Database\Odbc\OdbcDataSourceConfig;

/**
 * Native PHP ODBC prepared-statement executor for guarded CRUD commands.
 */
final class OdbcCrudNativePreparedExecutor implements OdbcCrudPreparedExecutorInterface
{
    private OdbcDataSourceConfig $config;
    private OdbcCrudGuard $guard;
    private OdbcCrudSqlBuilder $builder;

    public function __construct(OdbcDataSourceConfig $config, ?OdbcCrudGuard $guard = null, ?OdbcCrudSqlBuilder $builder = null)
    {
        $this->config = $config;
        $this->guard = $guard ?? new OdbcCrudGuard();
        $this->builder = $builder ?? new OdbcCrudSqlBuilder();
    }

    public function execute(OdbcCrudCommand $command, OdbcCrudCapabilities $capabilities, bool $aclGranted, bool $dryRun = false): OdbcCrudCommandResult
    {
        $this->guard->assertAllowed($command, $capabilities, $aclGranted);
        $plan = $this->builder->build($command);
        $audit = $this->guard->auditPreview($command) + [
            'executor' => 'OPUS_ODBC_CRUD_NATIVE_PREPARED_EXECUTOR_V1',
            'datasource' => $this->config->id(),
            'sql_plan' => $plan->toArray(),
            'dry_run_requested' => $dryRun,
        ];

        if ($dryRun) {
            if (!$capabilities->dryRunSupported()) {
                throw new \RuntimeException('OPUS_ODBC_CRUD_DRY_RUN_UNSUPPORTED: ' . $command->action());
            }
            return new OdbcCrudCommandResult($command->action(), $command->tableName(), 0, true, $audit);
        }

        NativeOdbcConnection::assertExtensionAvailable();
        $connection = @odbc_connect(
            $this->config->connectionTarget(),
            $this->config->username() ?? '',
            $this->config->password() ?? ''
        );

        if ($connection === false) {
            throw new \RuntimeException('OPUS_ODBC_CRUD_CONNECT_FAILED: ' . $this->config->id() . ': ' . (string) @odbc_errormsg());
        }

        $transactionStarted = false;
        try {
            if ($capabilities->transactionsSupported() && function_exists('odbc_autocommit')) {
                @odbc_autocommit($connection, false);
                $transactionStarted = true;
            }

            $statement = @odbc_prepare($connection, $plan->sql());
            if ($statement === false) {
                throw new \RuntimeException('OPUS_ODBC_CRUD_PREPARE_FAILED: ' . $command->action() . ': ' . (string) @odbc_errormsg($connection));
            }

            $ok = @odbc_execute($statement, $plan->parameters());
            if ($ok !== true) {
                throw new \RuntimeException('OPUS_ODBC_CRUD_EXECUTE_FAILED: ' . $command->action() . ': ' . (string) @odbc_errormsg($connection));
            }

            $affectedRows = @odbc_num_rows($statement);
            if (!is_int($affectedRows) || $affectedRows < 0) {
                $affectedRows = 0;
            }

            if ($transactionStarted) {
                @odbc_commit($connection);
                @odbc_autocommit($connection, true);
            }

            return new OdbcCrudCommandResult($command->action(), $command->tableName(), $affectedRows, false, $audit + ['transaction_started' => $transactionStarted]);
        } catch (\Throwable $e) {
            if ($transactionStarted && function_exists('odbc_rollback')) {
                @odbc_rollback($connection);
                @odbc_autocommit($connection, true);
            }
            throw $e;
        } finally {
            @odbc_close($connection);
        }
    }
}
