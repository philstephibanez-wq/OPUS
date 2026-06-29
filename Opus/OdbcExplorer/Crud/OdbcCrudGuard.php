<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

/**
 * Guard layer for ODBC Explorer CRUD before execution.
 */
final class OdbcCrudGuard
{
    public function assertAllowed(OdbcCrudCommand $command, OdbcCrudCapabilities $capabilities, bool $aclGranted): void
    {
        $capabilities->assertSupports($command->action());

        if (!$aclGranted) {
            throw new \RuntimeException('OPUS_ODBC_CRUD_ACL_DENIED: ' . $command->action());
        }

        if (trim($command->confirmationToken()) === '') {
            throw new \RuntimeException('OPUS_ODBC_CRUD_CONFIRMATION_REQUIRED: ' . $command->action());
        }

        $command->predicate()->assertNotEmptyFor($command->action());

        if (OdbcCrudAction::isDestructive($command->action()) && $command->predicate()->isEmpty()) {
            throw new \RuntimeException('OPUS_ODBC_CRUD_DESTRUCTIVE_PREDICATE_REQUIRED');
        }
    }

    /** @return array<string,mixed> */
    public function auditPreview(OdbcCrudCommand $command): array
    {
        return $command->auditContext() + [
            'guard' => 'OPUS_ODBC_CRUD_GUARD_V1',
            'confirmation_present' => trim($command->confirmationToken()) !== '',
        ];
    }
}
