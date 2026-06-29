<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Model\TableModel;

/**
 * Real six-stage LSTSAR engine for heterogeneous ODBC sources/destinations.
 */
final class LstsarModelDrivenOdbcEngine
{
    private LstsarOdbcSourceReaderInterface $sourceReader;
    private LstsarOdbcDestinationWriterInterface $destinationWriter;
    private ?LstsarStoreInterface $archiveStore;
    /** @var array<string,LstsarStageInterface> */
    private array $stages;

    /** @param array<string,LstsarStageInterface>|null $stages */
    public function __construct(LstsarOdbcSourceReaderInterface $sourceReader, LstsarOdbcDestinationWriterInterface $destinationWriter, ?LstsarStoreInterface $archiveStore = null, ?array $stages = null)
    {
        $this->sourceReader = $sourceReader;
        $this->destinationWriter = $destinationWriter;
        $this->archiveStore = $archiveStore;
        $this->stages = $stages ?? self::defaultStages();
        foreach (LstsarStageName::all() as $required) {
            if (!isset($this->stages[$required])) {
                throw new \InvalidArgumentException('OPUS_LSTSAR_MODEL_DRIVEN_STAGE_MISSING: ' . $required);
            }
            if (!$this->stages[$required] instanceof LstsarStageInterface || $this->stages[$required]->name() !== $required) {
                throw new \InvalidArgumentException('OPUS_LSTSAR_MODEL_DRIVEN_STAGE_INVALID: ' . $required);
            }
        }
    }

    /** @return array<string,LstsarStageInterface> */
    public static function defaultStages(): array
    {
        require_once __DIR__ . '/01_Load.php';
        require_once __DIR__ . '/02_Secure.php';
        require_once __DIR__ . '/03_Transform.php';
        require_once __DIR__ . '/04_Store.php';
        require_once __DIR__ . '/05_Archive.php';
        require_once __DIR__ . '/06_Report.php';

        return [
            LstsarStageName::LOAD => new LoadStage(),
            LstsarStageName::SECURIZE => new SecurizeStage(),
            LstsarStageName::TRANSFORM => new TransformStage(),
            LstsarStageName::STORE => new StoreStage(),
            LstsarStageName::ARCHIVE => new ArchiveStage(),
            LstsarStageName::REPORT => new ReportStage(),
        ];
    }

    /** @return array<string,LstsarStageInterface> */
    public function stages(): array
    {
        return $this->stages;
    }

    public function run(LstsarConfig $config, TableModel $sourceModel, TableModel $destinationModel): LstsarModelDrivenOdbcRunResult
    {
        $sourceRecord = $this->sourceReader->load($config, $sourceModel);
        $context = new LstsarContext($config, $sourceModel, $destinationModel, $sourceRecord);
        $stageResults = [];
        $events = [];
        $destinationRecordId = null;
        $archiveRecordId = null;
        $report = [];

        foreach (LstsarStageName::all() as $stageName) {
            $result = $this->stages[$stageName]->execute($context);
            foreach ($result->events() as $event) {
                $events[] = $event + ['run_id' => $config->runId()];
            }

            if (!$result->ok()) {
                $stageResults[$stageName] = $result;
                return LstsarModelDrivenOdbcRunResult::rejected($sourceRecord, $context->transformedRecord(), $stageResults, $result->violations(), $events);
            }

            if ($stageName === LstsarStageName::TRANSFORM) {
                $record = $result->payload()['transformed_record'] ?? [];
                if (!is_array($record)) {
                    throw new \RuntimeException('OPUS_LSTSAR_MODEL_DRIVEN_TRANSFORM_RECORD_INVALID');
                }
                $context = $context->withTransformedRecord($record);
            }

            if ($stageName === LstsarStageName::STORE) {
                $record = $context->transformedRecord();
                $destinationRecordId = $this->destinationWriter->store($config, $destinationModel, $record);
                $payload = $result->payload() + ['destination_record_id' => $destinationRecordId, 'stored' => true];
                $result = LstsarStageResult::success($stageName, $payload, array_merge($result->events(), [[
                    'stage' => $stageName,
                    'code' => 'OPUS_LSTSAR_MODEL_DRIVEN_DESTINATION_STORE_OK',
                    'destination_record_id' => $destinationRecordId,
                ]]));
                $events[] = ['stage' => $stageName, 'code' => 'OPUS_LSTSAR_MODEL_DRIVEN_DESTINATION_STORE_OK', 'run_id' => $config->runId(), 'destination_record_id' => $destinationRecordId];
            }

            if ($stageName === LstsarStageName::ARCHIVE && $this->archiveStore !== null && (($config->archive()['enabled'] ?? true) === true)) {
                $archiveRecordId = $this->archiveStore->store($config->runId(), [
                    'contract' => 'OPUS_LSTSAR_MODEL_DRIVEN_ARCHIVE_RECORD_V1',
                    'config' => $config->toArray(),
                    'context' => $context->toArray(),
                    'destination_record_id' => $destinationRecordId,
                    'stage_results' => array_map(static fn (LstsarStageResult $r): array => $r->toArray(), $stageResults),
                ]);
                $payload = $result->payload() + ['archive_record_id' => $archiveRecordId, 'archived' => true];
                $result = LstsarStageResult::success($stageName, $payload, array_merge($result->events(), [[
                    'stage' => $stageName,
                    'code' => 'OPUS_LSTSAR_MODEL_DRIVEN_ARCHIVE_OK',
                    'archive_record_id' => $archiveRecordId,
                ]]));
                $events[] = ['stage' => $stageName, 'code' => 'OPUS_LSTSAR_MODEL_DRIVEN_ARCHIVE_OK', 'run_id' => $config->runId(), 'archive_record_id' => $archiveRecordId];
            }

            $stageResults[$stageName] = $result;
            $context = $context->withStagePayload($stageName, $result->payload());
        }

        $report = [
            'contract' => 'OPUS_LSTSAR_MODEL_DRIVEN_ODBC_REPORT_V1',
            'run_id' => $config->runId(),
            'source' => $config->source(),
            'destination' => $config->destination(),
            'destination_record_id' => $destinationRecordId,
            'archive_record_id' => $archiveRecordId,
            'stages' => array_keys($stageResults),
            'ok' => true,
        ];

        if ($destinationRecordId === null) {
            throw new \RuntimeException('OPUS_LSTSAR_MODEL_DRIVEN_DESTINATION_RECORD_MISSING');
        }

        return LstsarModelDrivenOdbcRunResult::stored($destinationRecordId, $archiveRecordId, $sourceRecord, $context->transformedRecord(), $stageResults, $events, $report);
    }
}
