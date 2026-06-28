<?php
declare(strict_types=1);

namespace Opus\Lstsar\Engine;

use Opus\Lstsar\LstsarReportInterface;
use Opus\Lstsar\Stage\LstsarStageResult;

/**
 * Immutable LSTSAR pipeline run report.
 */
final class LstsarPipelineRunReport implements LstsarReportInterface
{
    private string $id;
    private string $jobId;
    private string $status;
    /** @var list<LstsarStageResult> */
    private array $stageResults;
    /** @var list<array<string,mixed>> */
    private array $auditTrail;

    /**
     * @param list<LstsarStageResult> $stageResults
     * @param list<array<string,mixed>> $auditTrail
     */
    public function __construct(string $id, string $jobId, string $status, array $stageResults, array $auditTrail)
    {
        if (trim($id) === '') {
            throw new \InvalidArgumentException('OPUS_LSTSAR_REPORT_ID_EMPTY');
        }
        if (trim($jobId) === '') {
            throw new \InvalidArgumentException('OPUS_LSTSAR_REPORT_JOB_ID_EMPTY');
        }

        $this->id = $id;
        $this->jobId = $jobId;
        $this->status = $status;
        $this->stageResults = $stageResults;
        $this->auditTrail = $auditTrail;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function jobId(): string
    {
        return $this->jobId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function auditTrail(): array
    {
        return $this->auditTrail;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->jobId,
            'status' => $this->status,
            'stage_results' => array_map(static fn (LstsarStageResult $result): array => $result->toArray(), $this->stageResults),
            'audit_trail' => $this->auditTrail,
        ];
    }
}
