<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * In-memory LSTSAR store used by tests, demos and smoke contracts.
 */
final class InMemoryLstsarStore implements LstsarStoreInterface, InMemoryLstsarStoreInterface
{
    /** @var array<string,array<string,array<string,mixed>>> */
    private array $records = [];

    /** @var array<string,list<array<string,mixed>>> */
    private array $audit = [];

    /** @param array<string,mixed> $record */
    public function store(string $datasetId, array $record): string
    {
        $recordId = hash('sha256', $datasetId . ':' . json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->records[$datasetId][$recordId] = $record;

        return $recordId;
    }

    public function restore(string $datasetId, string $recordId): array
    {
        if (!isset($this->records[$datasetId][$recordId])) {
            throw new \RuntimeException('OPUS_LSTSAR_RECORD_NOT_FOUND: ' . $datasetId . ':' . $recordId);
        }

        $this->audit($datasetId, [
            'stage' => 'restore',
            'code' => 'OPUS_LSTSAR_RESTORE_OK',
            'record_id' => $recordId,
        ]);

        return $this->records[$datasetId][$recordId];
    }

    public function audit(string $datasetId, array $event): void
    {
        $event['dataset_id'] = $datasetId;
        $event['index'] = count($this->audit[$datasetId] ?? []);
        $this->audit[$datasetId][] = $event;
    }

    public function auditTrail(string $datasetId): array
    {
        return $this->audit[$datasetId] ?? [];
    }
}
