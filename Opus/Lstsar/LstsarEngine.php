<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Security\Access\AccessDecisionInterface;

/**
 * OPUS LSTSAR engine.
 *
 * LSTSAR means Load, Secure, Transform, Store, Audit, Restore.
 * Security is not reimplemented here: callers must inject an already computed
 * OPUS access decision from the SSO/API/ACL layer.
 */
final class LstsarEngine
{
    private LstsarStoreInterface $store;

    public function __construct(LstsarStoreInterface $store)
    {
        $this->store = $store;
    }

    /**
     * @param array<string,mixed> $schema
     * @param array<string,mixed> $source
     */
    public function process(string $datasetId, array $schema, array $source, AccessDecisionInterface $accessDecision): LstsarResult
    {
        $this->assertSchema($schema);

        $this->store->audit($datasetId, ['stage' => 'load', 'code' => 'OPUS_LSTSAR_LOAD_OK', 'fields' => array_keys($source)]);

        if (!$accessDecision->isGranted()) {
            $violation = new LstsarViolation('secure', '*', 'OPUS_LSTSAR_SECURE_DENIED', $accessDecision->reason(), $accessDecision->toArray());
            $this->store->audit($datasetId, ['stage' => 'secure', 'code' => 'OPUS_LSTSAR_SECURE_DENIED', 'decision' => $accessDecision->toArray()]);

            return LstsarResult::rejected([$violation]);
        }

        $this->store->audit($datasetId, ['stage' => 'secure', 'code' => 'OPUS_LSTSAR_SECURE_GRANTED', 'decision' => $accessDecision->toArray()]);

        $sourceViolations = $this->validateStage('source', $source, (array) $schema['fields']);
        if ($sourceViolations !== []) {
            $this->store->audit($datasetId, ['stage' => 'source', 'code' => 'OPUS_LSTSAR_SOURCE_REJECTED', 'violations' => array_map(static fn (LstsarViolation $v): array => $v->toArray(), $sourceViolations)]);

            return LstsarResult::rejected($sourceViolations);
        }

        $target = $this->transform($source, (array) $schema['fields']);
        $this->store->audit($datasetId, ['stage' => 'transform', 'code' => 'OPUS_LSTSAR_TRANSFORM_OK', 'fields' => array_keys($target)]);

        $targetViolations = $this->validateStage('target', $target, (array) $schema['fields']);
        if ($targetViolations !== []) {
            $this->store->audit($datasetId, ['stage' => 'target', 'code' => 'OPUS_LSTSAR_TARGET_REJECTED', 'violations' => array_map(static fn (LstsarViolation $v): array => $v->toArray(), $targetViolations)]);

            return LstsarResult::rejected($targetViolations);
        }

        $recordId = $this->store->store($datasetId, $target);
        $this->store->audit($datasetId, ['stage' => 'store', 'code' => 'OPUS_LSTSAR_STORE_OK', 'record_id' => $recordId]);
        $this->store->audit($datasetId, ['stage' => 'audit', 'code' => 'OPUS_LSTSAR_AUDIT_OK']);

        return LstsarResult::stored($recordId, $target);
    }

    /** @return array<string,mixed> */
    public function restore(string $datasetId, string $recordId): array
    {
        return $this->store->restore($datasetId, $recordId);
    }

    /** @return list<array<string,mixed>> */
    public function auditTrail(string $datasetId): array
    {
        return $this->store->auditTrail($datasetId);
    }

    /** @param array<string,mixed> $schema */
    private function assertSchema(array $schema): void
    {
        if (($schema['contract'] ?? '') !== 'OPUS_LSTSAR_SCHEMA_V1') {
            throw new \RuntimeException('OPUS_LSTSAR_SCHEMA_CONTRACT_INVALID');
        }

        if (!isset($schema['fields']) || !is_array($schema['fields']) || $schema['fields'] === []) {
            throw new \RuntimeException('OPUS_LSTSAR_SCHEMA_FIELDS_EMPTY');
        }
    }

    /**
     * @param array<string,mixed> $record
     * @param array<string,mixed> $fields
     * @return list<LstsarViolation>
     */
    private function validateStage(string $stage, array $record, array $fields): array
    {
        $violations = [];

        foreach ($fields as $field => $config) {
            if (!is_array($config)) {
                $violations[] = new LstsarViolation($stage, (string) $field, 'OPUS_LSTSAR_FIELD_CONFIG_INVALID', 'Field configuration must be an array.');
                continue;
            }

            $rules = $config[$stage] ?? null;
            if ($rules === null) {
                continue;
            }
            if (!is_array($rules)) {
                $violations[] = new LstsarViolation($stage, (string) $field, 'OPUS_LSTSAR_RULES_INVALID', 'Stage rules must be an array.');
                continue;
            }

            if (!array_key_exists((string) $field, $record)) {
                if (($rules['required'] ?? true) === true) {
                    $violations[] = new LstsarViolation($stage, (string) $field, 'OPUS_LSTSAR_FIELD_REQUIRED', 'Required field is missing.');
                }
                continue;
            }

            $value = $record[(string) $field];
            $violations = array_merge($violations, $this->validateValue($stage, (string) $field, $value, $rules));
        }

        return $violations;
    }

    /**
     * @param mixed $value
     * @param array<string,mixed> $rules
     * @return list<LstsarViolation>
     */
    private function validateValue(string $stage, string $field, $value, array $rules): array
    {
        $violations = [];
        $type = (string) ($rules['type'] ?? '');

        if ($type !== '' && !$this->matchesType($value, $type)) {
            $violations[] = new LstsarViolation($stage, $field, 'OPUS_LSTSAR_TYPE_INVALID', 'Unexpected value type.', ['expected' => $type, 'actual' => gettype($value)]);

            return $violations;
        }

        if (is_string($value)) {
            $length = strlen($value);
            if (isset($rules['min_length']) && $length < (int) $rules['min_length']) {
                $violations[] = new LstsarViolation($stage, $field, 'OPUS_LSTSAR_MIN_LENGTH_INVALID', 'String is shorter than expected.', ['min_length' => (int) $rules['min_length'], 'actual_length' => $length]);
            }
            if (isset($rules['max_length']) && $length > (int) $rules['max_length']) {
                $violations[] = new LstsarViolation($stage, $field, 'OPUS_LSTSAR_MAX_LENGTH_INVALID', 'String is longer than expected.', ['max_length' => (int) $rules['max_length'], 'actual_length' => $length]);
            }
            if (isset($rules['exact_length']) && $length !== (int) $rules['exact_length']) {
                $violations[] = new LstsarViolation($stage, $field, 'OPUS_LSTSAR_EXACT_LENGTH_INVALID', 'String length is not the expected length.', ['exact_length' => (int) $rules['exact_length'], 'actual_length' => $length]);
            }
            if (isset($rules['max_bytes']) && strlen($value) > (int) $rules['max_bytes']) {
                $violations[] = new LstsarViolation($stage, $field, 'OPUS_LSTSAR_MAX_BYTES_INVALID', 'String byte size is larger than expected.', ['max_bytes' => (int) $rules['max_bytes'], 'actual_bytes' => strlen($value)]);
            }
        }

        if ((is_int($value) || is_float($value)) && isset($rules['min']) && $value < (float) $rules['min']) {
            $violations[] = new LstsarViolation($stage, $field, 'OPUS_LSTSAR_MIN_VALUE_INVALID', 'Numeric value is lower than expected.', ['min' => $rules['min'], 'actual' => $value]);
        }

        if ((is_int($value) || is_float($value)) && isset($rules['max']) && $value > (float) $rules['max']) {
            $violations[] = new LstsarViolation($stage, $field, 'OPUS_LSTSAR_MAX_VALUE_INVALID', 'Numeric value is greater than expected.', ['max' => $rules['max'], 'actual' => $value]);
        }

        if (isset($rules['precision']) && (is_int($value) || is_float($value))) {
            $violations = array_merge($violations, $this->validatePrecisionScale($stage, $field, $value, $rules));
        }

        return $violations;
    }

    /** @param array<string,mixed> $rules */
    private function validatePrecisionScale(string $stage, string $field, $value, array $rules): array
    {
        $violations = [];
        $text = rtrim(rtrim(sprintf('%.14F', (float) $value), '0'), '.');
        $unsigned = ltrim($text, '-');
        $parts = explode('.', $unsigned, 2);
        $digits = strlen(str_replace('.', '', $unsigned));
        $scale = isset($parts[1]) ? strlen($parts[1]) : 0;

        if ($digits > (int) $rules['precision']) {
            $violations[] = new LstsarViolation($stage, $field, 'OPUS_LSTSAR_PRECISION_INVALID', 'Numeric precision is greater than expected.', ['precision' => (int) $rules['precision'], 'actual_precision' => $digits]);
        }

        if (isset($rules['scale']) && $scale > (int) $rules['scale']) {
            $violations[] = new LstsarViolation($stage, $field, 'OPUS_LSTSAR_SCALE_INVALID', 'Numeric scale is greater than expected.', ['scale' => (int) $rules['scale'], 'actual_scale' => $scale]);
        }

        return $violations;
    }

    private function matchesType($value, string $type): bool
    {
        if ($type === 'string') {
            return is_string($value);
        }
        if ($type === 'int' || $type === 'integer') {
            return is_int($value);
        }
        if ($type === 'float' || $type === 'number') {
            return is_float($value) || is_int($value);
        }
        if ($type === 'bool' || $type === 'boolean') {
            return is_bool($value);
        }
        if ($type === 'array') {
            return is_array($value);
        }

        throw new \RuntimeException('OPUS_LSTSAR_TYPE_UNSUPPORTED: ' . $type);
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    private function transform(array $source, array $fields): array
    {
        $target = [];

        foreach ($fields as $field => $config) {
            if (!is_array($config)) {
                continue;
            }

            $field = (string) $field;
            if (!array_key_exists($field, $source)) {
                continue;
            }

            $value = $source[$field];
            $transform = $config['transform'] ?? [];
            if (!is_array($transform)) {
                throw new \RuntimeException('OPUS_LSTSAR_TRANSFORM_CONFIG_INVALID: ' . $field);
            }

            $target[$field] = $this->transformValue($value, $transform);
        }

        return $target;
    }

    /** @param array<string,mixed> $transform */
    private function transformValue($value, array $transform)
    {
        if (($transform['trim'] ?? false) === true && is_string($value)) {
            $value = trim($value);
        }

        if (($transform['uppercase'] ?? false) === true && is_string($value)) {
            $value = strtoupper($value);
        }

        if (($transform['lowercase'] ?? false) === true && is_string($value)) {
            $value = strtolower($value);
        }

        if (isset($transform['cast'])) {
            $cast = (string) $transform['cast'];
            if ($cast === 'string') {
                $value = (string) $value;
            } elseif ($cast === 'int' || $cast === 'integer') {
                $value = (int) $value;
            } elseif ($cast === 'float' || $cast === 'number') {
                $value = (float) $value;
            } elseif ($cast === 'bool' || $cast === 'boolean') {
                $value = (bool) $value;
            } else {
                throw new \RuntimeException('OPUS_LSTSAR_CAST_UNSUPPORTED: ' . $cast);
            }
        }

        if (isset($transform['pad_right']) && is_string($value) && is_array($transform['pad_right'])) {
            $value = str_pad($value, (int) ($transform['pad_right']['length'] ?? strlen($value)), (string) ($transform['pad_right']['char'] ?? ' '));
        }

        if (isset($transform['round']) && (is_float($value) || is_int($value))) {
            $value = round((float) $value, (int) $transform['round']);
        }

        return $value;
    }
}
