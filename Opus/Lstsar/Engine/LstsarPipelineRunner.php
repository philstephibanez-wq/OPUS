<?php
declare(strict_types=1);

namespace Opus\Lstsar\Engine;

use Opus\Lstsar\LstsarJobInterface;
use Opus\Lstsar\LstsarPipelineInterface;
use Opus\Lstsar\LstsarReportInterface;
use Opus\Lstsar\Stage\LstsarStageResult;

/**
 * Contract-only LSTSAR pipeline runner skeleton.
 *
 * It verifies pipeline/job compatibility and produces a planned report. It does not
 * load, transform, store or persist anything in this milestone.
 */
final class LstsarPipelineRunner
{
    public function dryRun(LstsarPipelineInterface $pipeline, LstsarJobInterface $job): LstsarReportInterface
    {
        if ($pipeline->id() !== $job->pipelineId()) {
            throw new \RuntimeException('OPUS_LSTSAR_JOB_PIPELINE_MISMATCH: ' . $job->id());
        }

        $stageResults = [];
        foreach ($pipeline->stageOrder() as $stage) {
            $stageResults[] = LstsarStageResult::planned($stage, [
                'job_id' => $job->id(),
                'pipeline_id' => $pipeline->id(),
            ]);
        }

        return new LstsarPipelineRunReport(
            'lstsar-report-' . $job->id(),
            $job->id(),
            'contract_skeleton_not_executed',
            $stageResults,
            [
                [
                    'event' => 'pipeline.selected',
                    'pipeline_id' => $pipeline->id(),
                ],
                [
                    'event' => 'pipeline.not_executed',
                    'reason' => 'P7_LSTSAR_CONTRACT_ENGINE_SKELETON',
                ],
            ]
        );
    }
}
