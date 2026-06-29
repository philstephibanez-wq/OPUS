<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

/**
 * High-level service for guarded CRUD execution.
 */
final class OdbcCrudService
{
    private OdbcCrudPreparedExecutorInterface $executor;
    private OdbcCrudCapabilities $capabilities;

    public function __construct(OdbcCrudPreparedExecutorInterface $executor, OdbcCrudCapabilities $capabilities)
    {
        $this->executor = $executor;
        $this->capabilities = $capabilities;
    }

    public function execute(OdbcCrudCommand $command, bool $aclGranted, bool $dryRun = false): OdbcCrudCommandResult
    {
        return $this->executor->execute($command, $this->capabilities, $aclGranted, $dryRun);
    }

    public function dryRun(OdbcCrudCommand $command, bool $aclGranted): OdbcCrudCommandResult
    {
        return $this->execute($command, $aclGranted, true);
    }
}
