<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Stage 03: Transform source model fields into destination model fields.
 */
final class TransformStage implements LstsarStageInterface
{
    public function name(): string
    {
        return LstsarStageName::TRANSFORM;
    }

    public function execute(LstsarContext $context): LstsarStageResult
    {
        $record = [];
        $source = $context->sourceRecord();
        $transforms = $context->config()->transform();

        foreach ($context->config()->mapping() as $sourceField => $destinationField) {
            if (!array_key_exists($sourceField, $source)) {
                continue;
            }
            $value = $source[$sourceField];
            $rules = $transforms[$destinationField] ?? [];
            if (!is_array($rules)) {
                throw new \RuntimeException('OPUS_LSTSAR_TRANSFORM_RULES_INVALID: ' . $destinationField);
            }
            $record[$destinationField] = $this->transformValue($value, $rules);
        }

        return LstsarStageResult::success($this->name(), [
            'transformed_record' => $record,
            'destination_model' => $context->destinationModel()->id(),
        ], [[
            'stage' => $this->name(),
            'code' => 'OPUS_LSTSAR_TRANSFORM_MODEL_MAPPING_OK',
            'fields' => array_keys($record),
        ]]);
    }

    /** @param array<string,mixed> $rules */
    private function transformValue(mixed $value, array $rules): mixed
    {
        if (($rules['trim'] ?? false) === true && is_string($value)) {
            $value = trim($value);
        }
        if (($rules['uppercase'] ?? false) === true && is_string($value)) {
            $value = strtoupper($value);
        }
        if (($rules['lowercase'] ?? false) === true && is_string($value)) {
            $value = strtolower($value);
        }
        if (isset($rules['cast'])) {
            $cast = (string) $rules['cast'];
            if ($cast === 'string') {
                $value = (string) $value;
            } elseif ($cast === 'integer' || $cast === 'int') {
                $value = (int) $value;
            } elseif ($cast === 'float' || $cast === 'number') {
                $value = (float) $value;
            } elseif ($cast === 'boolean' || $cast === 'bool') {
                $value = (bool) $value;
            } else {
                throw new \RuntimeException('OPUS_LSTSAR_TRANSFORM_CAST_UNSUPPORTED: ' . $cast);
            }
        }
        if (isset($rules['round']) && (is_int($value) || is_float($value))) {
            $value = round((float) $value, (int) $rules['round']);
        }

        return $value;
    }
}
