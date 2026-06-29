<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Model\TableModel;
use Opus\OdbcExplorer\Crud\OdbcCrudCommand;
use Opus\OdbcExplorer\Crud\OdbcCrudService;

/**
 * Destination writer that stores through the guarded ODBC CRUD service.
 */
final class LstsarOdbcCrudDestinationWriter implements LstsarOdbcDestinationWriterInterface
{
    private OdbcCrudService $crudService;
    private bool $aclGranted;

    public function __construct(OdbcCrudService $crudService, bool $aclGranted = true)
    {
        $this->crudService = $crudService;
        $this->aclGranted = $aclGranted;
    }

    public function store(LstsarConfig $config, TableModel $destinationModel, array $record): string
    {
        $security = $config->security();
        $actorId = trim((string) ($security['actor_id'] ?? 'lstsar'));
        $confirmationToken = trim((string) ($security['confirmation_token'] ?? 'OPUS_LSTSAR_CONFIRMED'));
        $requestId = trim((string) ($security['request_id'] ?? $config->runId()));

        $command = OdbcCrudCommand::insert($destinationModel, $record, $actorId, $confirmationToken, $requestId);
        $result = $this->crudService->execute($command, $this->aclGranted, false);

        return hash('sha256', $config->runId() . ':' . json_encode($result->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
