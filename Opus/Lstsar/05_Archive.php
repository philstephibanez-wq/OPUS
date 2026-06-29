<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Stage 05: Archive payload and run metadata for replay/audit purposes.
 */
final class ArchiveStage implements LstsarStageInterface
{
    public function name(): string
    {
        return LstsarStageName::ARCHIVE;
    }

    public function execute(LstsarContext $context): LstsarStageResult
    {
        $archive = $context->config()->archive();

        return LstsarStageResult::success($this->name(), [
            'enabled' => ($archive['enabled'] ?? true) === true,
            'policy' => $archive['policy'] ?? 'default-retention',
            'run_id' => $context->config()->runId(),
        ], [[
            'stage' => $this->name(),
            'code' => 'OPUS_LSTSAR_ARCHIVE_PLAN_OK',
        ]]);
    }
}
