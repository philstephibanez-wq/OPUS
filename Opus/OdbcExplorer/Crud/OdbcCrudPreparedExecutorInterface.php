<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

/**
 * Executes guarded CRUD commands through prepared ODBC statements.
 */
interface OdbcCrudPreparedExecutorInterface
{
    public function execute(OdbcCrudCommand $command, OdbcCrudCapabilities $capabilities, bool $aclGranted, bool $dryRun = false): OdbcCrudCommandResult;
}
