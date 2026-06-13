<?php

declare(strict_types=1);

namespace Opus\Lstsa;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaTransformPhase belongs to the LSTSA Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LSTSA domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - lstsa-overview
 *   diagrams:
 *     - lstsa-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LSTSAR TRANSFORM PHASE
 *
 * @visibility public
 * @role Applies allow-listed transformations from the declarative Lstsa mapping.
 * @contract Unknown transforms fail explicitly. Transform does not validate the
 *           output; SecureOutputPhase owns that responsibility.
 * @sideEffects Populates transformed rows in the current run context.
 */
final class LstsaTransformPhase implements LstsaPhaseInterface
{
    public function execute(LstsaPipelineContext $context): void
    {
        foreach ($context->acceptedRows as $row) {
            $outputRow = [];
            foreach ($context->definition->mappings() as $mapping) {
                $value = $row[$mapping->source] ?? null;
                foreach ($mapping->transforms as $transform) {
                    $value = $this->applyTransform($transform, $value);
                }
                $outputRow[$mapping->target] = $value;
            }
            $context->transformedRows[] = $outputRow;
        }

        $context->counts['transformed'] = count($context->transformedRows);
    }

    private function applyTransform(string $transform, mixed $value): mixed
    {
        return match ($transform) {
            'trim' => is_scalar($value) ? trim((string)$value) : $value,
            'lower' => is_scalar($value) ? strtolower((string)$value) : $value,
            'upper' => is_scalar($value) ? strtoupper((string)$value) : $value,
            'status_to_bool' => $this->statusToBool($value),
            default => throw new \RuntimeException('OPUS_Lstsa_TRANSFORM_NOT_ALLOWLISTED: ' . $transform),
        };
    }

    private function statusToBool(mixed $value): bool
    {
        $status = strtolower(trim((string)$value));
        return in_array($status, ['active', 'enabled', 'yes', 'true', '1'], true);
    }
}
