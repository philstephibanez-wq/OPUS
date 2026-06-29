<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Model\ModelRecord;

/**
 * Stage 04: Validate destination-model write readiness before ODBC storage.
 */
final class StoreStage implements LstsarStageInterface
{
    public function name(): string
    {
        return LstsarStageName::STORE;
    }

    public function execute(LstsarContext $context): LstsarStageResult
    {
        $record = $context->transformedRecord();
        if ($record === []) {
            $payload = $context->stagePayload(LstsarStageName::TRANSFORM);
            $record = isset($payload['transformed_record']) && is_array($payload['transformed_record']) ? $payload['transformed_record'] : [];
        }

        try {
            new ModelRecord($context->destinationModel(), $record);
        } catch (\Throwable $exception) {
            return LstsarStageResult::rejected($this->name(), [
                new LstsarViolation($this->name(), '*', 'OPUS_LSTSAR_STORE_DESTINATION_MODEL_INVALID', $exception->getMessage()),
            ]);
        }

        return LstsarStageResult::success($this->name(), [
            'destination_datasource' => $context->config()->destination()['datasource'] ?? null,
            'destination_model' => $context->destinationModel()->id(),
            'store_ready' => true,
            'record_fields' => array_keys($record),
        ], [[
            'stage' => $this->name(),
            'code' => 'OPUS_LSTSAR_STORE_MODEL_READY',
        ]]);
    }
}
