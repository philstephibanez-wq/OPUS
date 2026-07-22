<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Stage 03: Transform source model fields into destination model fields.
 *
 * This stage now supports two complementary destination-field sources:
 *
 * - mapping: source field -> destination field;
 * - assignments: extra destination fields supplied by constants, metadata,
 *   hashes, deterministic built-ins or registered pure hooks.
 */
final class TransformStage implements LstsarStageInterface, TransformStageInterface
{
    private LstsarTransformHookRegistry $hooks;

    public function __construct(?LstsarTransformHookRegistry $hooks = null)
    {
        $this->hooks = $hooks ?? LstsarTransformHookRegistry::empty();
    }

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
            $rules = $this->rulesForDestinationField($transforms, (string) $destinationField);
            if (!array_key_exists($sourceField, $source)) {
                if (array_key_exists('default', $rules)) {
                    $record[$destinationField] = $rules['default'];
                }
                continue;
            }
            $record[$destinationField] = $this->transformValue($source[$sourceField], $rules);
        }

        foreach ($this->assignments($transforms) as $destinationField => $assignment) {
            $destinationField = (string) $destinationField;
            $this->assertDestinationAssignmentField($context, $destinationField);
            $record[$destinationField] = $this->computeAssignment($context, $record, $destinationField, $assignment);
        }

        return LstsarStageResult::success($this->name(), [
            'transformed_record' => $record,
            'destination_model' => $context->destinationModel()->id(),
            'assigned_fields' => array_keys($this->assignments($transforms)),
            'hook_names' => $this->hooks->names(),
        ], [[
            'stage' => $this->name(),
            'code' => 'OPUS_LSTSAR_TRANSFORM_MODEL_MAPPING_OK',
            'fields' => array_keys($record),
        ]]);
    }

    /** @param array<string,mixed> $transforms @return array<string,mixed> */
    private function rulesForDestinationField(array $transforms, string $destinationField): array
    {
        $rules = [];
        if (isset($transforms['fields']) && is_array($transforms['fields'])) {
            $rules = $transforms['fields'][$destinationField] ?? [];
        } elseif (isset($transforms[$destinationField])) {
            $rules = $transforms[$destinationField];
        }

        if (!is_array($rules)) {
            throw new \RuntimeException('OPUS_LSTSAR_TRANSFORM_RULES_INVALID: ' . $destinationField);
        }

        return $rules;
    }

    /** @param array<string,mixed> $transforms @return array<string,array<string,mixed>> */
    private function assignments(array $transforms): array
    {
        $assignments = $transforms['assignments'] ?? [];
        if ($assignments === []) {
            return [];
        }
        if (!is_array($assignments)) {
            throw new \RuntimeException('OPUS_LSTSAR_TRANSFORM_ASSIGNMENTS_INVALID');
        }

        $out = [];
        foreach ($assignments as $field => $assignment) {
            if (!is_array($assignment)) {
                throw new \RuntimeException('OPUS_LSTSAR_TRANSFORM_ASSIGNMENT_INVALID: ' . (string) $field);
            }
            $out[(string) $field] = $assignment;
        }

        return $out;
    }

    private function assertDestinationAssignmentField(LstsarContext $context, string $destinationField): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $destinationField)) {
            throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_DESTINATION_FIELD_INVALID: ' . $destinationField);
        }
        if (!$context->destinationModel()->hasField($destinationField)) {
            throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_DESTINATION_FIELD_UNKNOWN: ' . $destinationField);
        }
    }

    /**
     * @param array<string,mixed> $destinationRecord
     * @param array<string,mixed> $assignment
     */
    private function computeAssignment(LstsarContext $context, array $destinationRecord, string $destinationField, array $assignment): mixed
    {
        $type = (string) ($assignment['type'] ?? (array_key_exists('value', $assignment) ? 'constant' : ''));
        if ($type === '') {
            throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_TYPE_MISSING: ' . $destinationField);
        }

        if ($type === 'constant') {
            if (!array_key_exists('value', $assignment)) {
                throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_CONSTANT_VALUE_MISSING: ' . $destinationField);
            }
            return $assignment['value'];
        }

        if ($type === 'now') {
            $timezone = new \DateTimeZone((string) ($assignment['timezone'] ?? 'UTC'));
            $format = (string) ($assignment['format'] ?? \DateTimeInterface::ATOM);

            return (new \DateTimeImmutable('now', $timezone))->format($format);
        }

        if ($type === 'metadata') {
            return $this->valueByPath($context->config()->metadata(), (string) ($assignment['path'] ?? ''), $assignment['default'] ?? null);
        }

        if ($type === 'security') {
            return $this->valueByPath($context->config()->security(), (string) ($assignment['path'] ?? ''), $assignment['default'] ?? null);
        }

        if ($type === 'source') {
            return $this->valueByPath($context->sourceRecord(), (string) ($assignment['path'] ?? ''), $assignment['default'] ?? null);
        }

        if ($type === 'destination' || $type === 'transformed') {
            return $this->valueByPath($destinationRecord, (string) ($assignment['path'] ?? ''), $assignment['default'] ?? null);
        }

        if ($type === 'hash') {
            return $this->hashAssignment($context, $destinationRecord, $assignment);
        }

        if ($type === 'concat') {
            return $this->concatAssignment($context, $destinationRecord, $assignment);
        }

        if ($type === 'hook') {
            $hookName = (string) ($assignment['hook'] ?? '');
            if ($hookName === '') {
                throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_HOOK_MISSING: ' . $destinationField);
            }

            return $this->hooks->compute($hookName, new LstsarTransformHookContext(
                $context->config(),
                $context->sourceModel(),
                $context->destinationModel(),
                $context->sourceRecord(),
                $destinationRecord,
                $destinationField,
                $assignment
            ));
        }

        throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_TYPE_UNSUPPORTED: ' . $type);
    }

    /** @param array<string,mixed> $assignment @param array<string,mixed> $destinationRecord */
    private function hashAssignment(LstsarContext $context, array $destinationRecord, array $assignment): string
    {
        $algo = (string) ($assignment['algo'] ?? 'sha256');
        if (!in_array($algo, hash_algos(), true)) {
            throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_HASH_ALGO_UNSUPPORTED: ' . $algo);
        }
        $source = (string) ($assignment['source'] ?? 'destination');
        $record = $source === 'source' ? $context->sourceRecord() : $destinationRecord;
        $fields = $assignment['fields'] ?? [];
        if (!is_array($fields) || $fields === []) {
            throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_HASH_FIELDS_MISSING');
        }

        $payload = [];
        foreach ($fields as $field) {
            $field = (string) $field;
            $payload[$field] = $record[$field] ?? null;
        }

        return hash($algo, (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string,mixed> $assignment @param array<string,mixed> $destinationRecord */
    private function concatAssignment(LstsarContext $context, array $destinationRecord, array $assignment): string
    {
        $parts = $assignment['parts'] ?? [];
        if (!is_array($parts) || $parts === []) {
            throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_CONCAT_PARTS_MISSING');
        }
        $separator = (string) ($assignment['separator'] ?? '');

        $values = [];
        foreach ($parts as $part) {
            if (!is_array($part)) {
                throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_CONCAT_PART_INVALID');
            }
            $partType = (string) ($part['type'] ?? 'constant');
            if ($partType === 'constant') {
                $values[] = (string) ($part['value'] ?? '');
            } elseif ($partType === 'source') {
                $values[] = (string) $this->valueByPath($context->sourceRecord(), (string) ($part['path'] ?? ''), $part['default'] ?? '');
            } elseif ($partType === 'destination' || $partType === 'transformed') {
                $values[] = (string) $this->valueByPath($destinationRecord, (string) ($part['path'] ?? ''), $part['default'] ?? '');
            } elseif ($partType === 'metadata') {
                $values[] = (string) $this->valueByPath($context->config()->metadata(), (string) ($part['path'] ?? ''), $part['default'] ?? '');
            } else {
                throw new \RuntimeException('OPUS_LSTSAR_ASSIGNMENT_CONCAT_PART_TYPE_UNSUPPORTED: ' . $partType);
            }
        }

        return implode($separator, $values);
    }

    /** @param array<string,mixed> $data */
    private function valueByPath(array $data, string $path, mixed $default = null): mixed
    {
        $path = trim($path);
        if ($path === '') {
            return $default;
        }

        $cursor = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
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
        if (isset($rules['pad_right']) && is_string($value) && is_array($rules['pad_right'])) {
            $value = str_pad($value, (int) ($rules['pad_right']['length'] ?? strlen($value)), (string) ($rules['pad_right']['char'] ?? ' '));
        }
        if (isset($rules['pad_left']) && is_string($value) && is_array($rules['pad_left'])) {
            $value = str_pad($value, (int) ($rules['pad_left']['length'] ?? strlen($value)), (string) ($rules['pad_left']['char'] ?? ' '), STR_PAD_LEFT);
        }

        return $value;
    }
}
