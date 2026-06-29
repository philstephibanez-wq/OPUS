<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Stage 01: Load source data from an ODBC-backed source model context.
 */
final class LoadStage implements LstsarStageInterface
{
    public function name(): string
    {
        return LstsarStageName::LOAD;
    }

    public function execute(LstsarContext $context): LstsarStageResult
    {
        return LstsarStageResult::success($this->name(), [
            'source_datasource' => $context->config()->source()['datasource'] ?? null,
            'source_model' => $context->sourceModel()->id(),
            'source_fields' => array_keys($context->sourceRecord()),
            'loaded' => true,
        ], [[
            'stage' => $this->name(),
            'code' => 'OPUS_LSTSAR_LOAD_CONTRACT_OK',
        ]]);
    }
}
