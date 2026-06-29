<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Stage 06: Produce functional and technical reporting metadata.
 */
final class ReportStage implements LstsarStageInterface
{
    public function name(): string
    {
        return LstsarStageName::REPORT;
    }

    public function execute(LstsarContext $context): LstsarStageResult
    {
        $report = $context->config()->report();

        return LstsarStageResult::success($this->name(), [
            'format' => $report['format'] ?? 'array',
            'run_id' => $context->config()->runId(),
            'source_model' => $context->sourceModel()->id(),
            'destination_model' => $context->destinationModel()->id(),
            'stage_count' => count(LstsarStageName::all()),
        ], [[
            'stage' => $this->name(),
            'code' => 'OPUS_LSTSAR_REPORT_PLAN_OK',
        ]]);
    }
}
