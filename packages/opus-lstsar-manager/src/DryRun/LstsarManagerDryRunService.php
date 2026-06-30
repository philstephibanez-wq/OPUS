<?php
declare(strict_types=1);

namespace OpusLstsarManager\DryRun;

use Opus\Lstsar\InMemoryLstsarStore;
use Opus\Lstsar\LstsarInMemoryOdbcDestinationWriter;
use Opus\Lstsar\LstsarInMemoryOdbcSourceReader;
use Opus\Lstsar\LstsarModelDrivenOdbcEngine;
use Opus\Lstsar\LstsarStageName;
use OpusLstsarManager\Config\LstsarManagerDeclarationRepository;

/**
 * Connects the LSTSAR Manager dry-run screen to the real model-driven ODBC engine.
 *
 * The service deliberately uses in-memory source/destination/archive boundaries:
 * it exercises the real six-stage engine without direct execution against a live
 * destination database, without DDL and without raw SQL.
 */
final class LstsarManagerDryRunService
{
    private LstsarManagerDeclarationRepository $repository;

    public function __construct(?LstsarManagerDeclarationRepository $repository = null)
    {
        $this->repository = $repository ?? new LstsarManagerDeclarationRepository();
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function preview(array $payload = []): array
    {
        $config = $this->repository->sampleConfigForPayload($payload);
        $sourceModel = $this->repository->sampleSourceModel();
        $destinationModel = $this->repository->sampleDestinationModel();
        $sourceRecord = $this->repository->sampleSourceRecord($payload);

        $sourceReader = new LstsarInMemoryOdbcSourceReader([
            $sourceModel->id() => $sourceRecord,
        ]);
        $destinationWriter = new LstsarInMemoryOdbcDestinationWriter();
        $archiveStore = new InMemoryLstsarStore();
        $engine = new LstsarModelDrivenOdbcEngine($sourceReader, $destinationWriter, $archiveStore);
        $result = $engine->run($config, $sourceModel, $destinationModel);
        $resultArray = $result->toArray();

        return [
            'contract' => 'OPUS_LSTSAR_MANAGER_DRY_RUN_INTEGRATION_V1',
            'dry_run' => true,
            'would_execute' => false,
            'execution_enabled' => false,
            'direct_execute_allowed' => false,
            'raw_sql_allowed' => false,
            'ddl_allowed' => false,
            'engine' => 'Opus\Lstsar\LstsarModelDrivenOdbcEngine',
            'stage_order' => LstsarStageName::all(),
            'source_model' => $sourceModel->toArray(),
            'destination_model' => $destinationModel->toArray(),
            'source_record' => $sourceRecord,
            'declaration' => $this->repository->sampleDeclarationArray(),
            'run_result' => $resultArray,
            'result_ok' => $result->ok(),
            'destination_record_id' => $result->destinationRecordId(),
            'archive_record_id' => $result->archiveRecordId(),
            'transformed_record' => $result->transformedRecord(),
            'destination_records' => $destinationWriter->records(),
            'archive_events' => $archiveStore->auditTrail($config->runId()),
            'simulated_boundaries' => [
                'source' => 'memory',
                'destination' => 'memory',
                'archive' => 'memory',
            ],
        ];
    }
}
