<?php
declare(strict_types=1);

namespace Opus\Lstsa;

use SimpleXMLElement;
/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaBatchExecutor belongs to the LSTSA Opus framework domain.
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

final class LstsaBatchExecutor
{
    private LstsaRunStore $store;

    public function __construct(LstsaRunStore $store)
    {
        $this->store = $store;
    }

    public function execute(array &$run, float $startedAt): array
    {
        $payload = $run['payload'] ?? [];
        if (!is_array($payload)) {
            throw new \RuntimeException('Lstsa payload must be an array');
        }

        $definitionXml = (string)($payload['definition_xml'] ?? '');
        if (trim($definitionXml) === '') {
            throw new \RuntimeException('Lstsa memory_batch payload missing definition_xml');
        }

        $xml = \simplexml_load_string($definitionXml);
        if (!$xml instanceof SimpleXMLElement) {
            throw new \RuntimeException('Lstsa memory_batch definition XML invalid');
        }

        $definition = (new LstsaConfigLoader())->fromXml($xml, (string)$run['run_id']);
        $rows = $payload['rows'] ?? [];
        if (!is_array($rows)) {
            throw new \RuntimeException('Lstsa memory_batch rows must be an array');
        }

        $batchSize = max(1, (int)($run['limits']['max_rows_per_batch'] ?? 1000));
        $maxRunSeconds = max(1, (int)($run['limits']['max_run_seconds'] ?? 300));
        $maxBatchSeconds = max(1, (int)($run['limits']['max_batch_seconds'] ?? 30));

        $counts = $this->emptyCounts();
        $storedRows = [];
        $rejectedRows = [];
        $batchIndex = 0;

        $this->store->heartbeat($run, 'LOAD', $counts);

        foreach (array_chunk($rows, $batchSize) as $batchRows) {
            $this->assertRunTime($startedAt, $maxRunSeconds, (string)$run['run_id']);
            $batchStarted = microtime(true);
            ++$batchIndex;

            $checkpoint = [
                'step' => 'BATCH_START',
                'batch_size' => count($batchRows),
                'counts_before' => $counts,
            ];

            foreach ($batchRows as $offset => $row) {
                $this->assertRunTime($startedAt, $maxRunSeconds, (string)$run['run_id']);
                if ((microtime(true) - $batchStarted) > $maxBatchSeconds) {
                    throw new LstsaRunnerTimeoutException('Lstsa max_batch_seconds exceeded for run: ' . $run['run_id']);
                }

                $counts['loaded']++;
                $absoluteIndex = (($batchIndex - 1) * $batchSize) + (int)$offset;

                if (!is_array($row)) {
                    $counts['rejected']++;
                    $counts['errors']++;
                    $rejectedRows[] = $this->rejection($absoluteIndex, $row, ['SECURE_INPUT:ROW:OPUS_Lstsa_ROW_NOT_ARRAY']);
                    continue;
                }

                $inputErrors = $this->validateInput($definition, $row);
                if ($inputErrors !== []) {
                    $counts['rejected']++;
                    $rejectedRows[] = $this->rejection($absoluteIndex, $row, $inputErrors);
                    continue;
                }

                $counts['accepted']++;

                [$outputRow, $outputErrors] = $this->transformRow($definition, $row);
                if ($outputErrors !== []) {
                    $counts['rejected']++;
                    $rejectedRows[] = $this->rejection($absoluteIndex, $row, $outputErrors, $outputRow);
                    continue;
                }

                $counts['transformed']++;
                $counts['stored']++;
                $storedRows[] = $outputRow;
            }

            $checkpoint['counts_after'] = $counts;
            $this->store->heartbeat($run, 'CHECKPOINT_BATCH_' . $batchIndex, $counts);
            $this->store->writeCheckpoint($run, $batchIndex, $checkpoint);
            $counts['checkpoints']++;
        }

        $this->store->heartbeat($run, 'ARCHIVE', $counts);
        $archivePath = $this->store->writeArchivePayload($run, 'stored_rows.json', [
            'definition_id' => $definition->id(),
            'definition_version' => $definition->version(),
            'store_connection' => $definition->storeConnection(),
            'store_table' => $definition->storeTable(),
            'store_mode' => $definition->storeMode(),
            'rows' => $storedRows,
        ]);
        $counts['archived'] = 1;

        $quarantinePath = null;
        if ($rejectedRows !== []) {
            $quarantinePath = $this->store->writeQuarantinePayload($run, 'rejected_rows.json', [
                'definition_id' => $definition->id(),
                'definition_version' => $definition->version(),
                'rows' => $rejectedRows,
            ]);
        }

        $status = $counts['rejected'] > 0
            ? ($counts['stored'] > 0 ? LstsaRunStatus::PARTIAL : LstsaRunStatus::QUARANTINED)
            : LstsaRunStatus::DONE;

        return [
            'status' => $status,
            'counts' => $counts,
            'artifacts' => [
                'archives' => [$archivePath],
                'quarantine' => $quarantinePath === null ? [] : [$quarantinePath],
            ],
        ];
    }

    private function validateInput(LstsaDefinition $definition, array $row): array
    {
        $errors = [];
        foreach ($definition->loadFields() as $fieldName => $constraint) {
            $value = $row[$fieldName] ?? null;
            $errors = array_merge($errors, $constraint->validate($value, 'SECURE_INPUT'));
        }

        foreach (array_keys($row) as $fieldName) {
            if (!array_key_exists((string)$fieldName, $definition->loadFields())) {
                $errors[] = 'SECURE_INPUT:' . (string)$fieldName . ':OPUS_Lstsa_FIELD_UNKNOWN';
            }
        }

        return $errors;
    }

    private function transformRow(LstsaDefinition $definition, array $row): array
    {
        $outputRow = [];
        $errors = [];

        foreach ($definition->mappings() as $mapping) {
            $value = $row[$mapping->source] ?? null;
            foreach ($mapping->transforms as $transform) {
                $value = $this->applyTransform($transform, $value);
            }

            $outputRow[$mapping->target] = $value;
            $errors = array_merge($errors, $mapping->constraint->validate($value, 'SECURE_OUTPUT'));
        }

        return [$outputRow, $errors];
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

    private function rejection(int $rowIndex, mixed $input, array $errors, ?array $output = null): array
    {
        return [
            'row_index' => $rowIndex,
            'errors' => $errors,
            'input' => $input,
            'output' => $output,
        ];
    }

    private function emptyCounts(): array
    {
        return [
            'loaded' => 0,
            'accepted' => 0,
            'transformed' => 0,
            'stored' => 0,
            'archived' => 0,
            'checkpoints' => 0,
            'rejected' => 0,
            'errors' => 0,
        ];
    }

    private function assertRunTime(float $startedAt, int $maxRunSeconds, string $runId): void
    {
        if ((microtime(true) - $startedAt) > $maxRunSeconds) {
            throw new LstsaRunnerTimeoutException('Lstsa max_run_seconds exceeded for run: ' . $runId);
        }
    }
}
