<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Final result for the six-stage model-driven ODBC LSTSAR engine.
 */
final class LstsarModelDrivenOdbcRunResult implements LstsarModelDrivenOdbcRunResultInterface
{
    private bool $ok;
    private ?string $destinationRecordId;
    private ?string $archiveRecordId;
    /** @var array<string,mixed> */
    private array $sourceRecord;
    /** @var array<string,mixed> */
    private array $transformedRecord;
    /** @var array<string,LstsarStageResult> */
    private array $stageResults;
    /** @var list<LstsarViolation> */
    private array $violations;
    /** @var list<array<string,mixed>> */
    private array $events;
    /** @var array<string,mixed> */
    private array $report;

    /**
     * @param array<string,mixed> $sourceRecord
     * @param array<string,mixed> $transformedRecord
     * @param array<string,LstsarStageResult> $stageResults
     * @param list<LstsarViolation> $violations
     * @param list<array<string,mixed>> $events
     * @param array<string,mixed> $report
     */
    private function __construct(bool $ok, ?string $destinationRecordId, ?string $archiveRecordId, array $sourceRecord, array $transformedRecord, array $stageResults, array $violations, array $events, array $report)
    {
        $this->ok = $ok;
        $this->destinationRecordId = $destinationRecordId;
        $this->archiveRecordId = $archiveRecordId;
        $this->sourceRecord = $sourceRecord;
        $this->transformedRecord = $transformedRecord;
        $this->stageResults = $stageResults;
        $this->violations = $violations;
        $this->events = $events;
        $this->report = $report;
    }

    /** @param array<string,mixed> $sourceRecord @param array<string,mixed> $transformedRecord @param array<string,LstsarStageResult> $stageResults @param list<array<string,mixed>> $events @param array<string,mixed> $report */
    public static function stored(string $destinationRecordId, ?string $archiveRecordId, array $sourceRecord, array $transformedRecord, array $stageResults, array $events, array $report): self
    {
        return new self(true, $destinationRecordId, $archiveRecordId, $sourceRecord, $transformedRecord, $stageResults, [], $events, $report);
    }

    /** @param array<string,mixed> $sourceRecord @param array<string,mixed> $transformedRecord @param array<string,LstsarStageResult> $stageResults @param list<LstsarViolation> $violations @param list<array<string,mixed>> $events */
    public static function rejected(array $sourceRecord, array $transformedRecord, array $stageResults, array $violations, array $events): self
    {
        return new self(false, null, null, $sourceRecord, $transformedRecord, $stageResults, $violations, $events, []);
    }

    public function ok(): bool
    {
        return $this->ok;
    }

    public function destinationRecordId(): ?string
    {
        return $this->destinationRecordId;
    }

    public function archiveRecordId(): ?string
    {
        return $this->archiveRecordId;
    }

    /** @return array<string,mixed> */
    public function sourceRecord(): array
    {
        return $this->sourceRecord;
    }

    /** @return array<string,mixed> */
    public function transformedRecord(): array
    {
        return $this->transformedRecord;
    }

    /** @return array<string,LstsarStageResult> */
    public function stageResults(): array
    {
        return $this->stageResults;
    }

    /** @return list<LstsarViolation> */
    public function violations(): array
    {
        return $this->violations;
    }

    /** @return list<array<string,mixed>> */
    public function events(): array
    {
        return $this->events;
    }

    /** @return array<string,mixed> */
    public function report(): array
    {
        return $this->report;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => 'OPUS_LSTSAR_MODEL_DRIVEN_ODBC_RUN_RESULT_V1',
            'ok' => $this->ok,
            'destination_record_id' => $this->destinationRecordId,
            'archive_record_id' => $this->archiveRecordId,
            'source_record' => $this->sourceRecord,
            'transformed_record' => $this->transformedRecord,
            'stage_results' => array_map(static fn (LstsarStageResult $result): array => $result->toArray(), $this->stageResults),
            'violations' => array_map(static fn (LstsarViolation $violation): array => $violation->toArray(), $this->violations),
            'events' => $this->events,
            'report' => $this->report,
        ];
    }
}
