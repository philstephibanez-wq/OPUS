<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * JSON-file LSTSAR store for OPUS API/runtime integration.
 */
final class JsonFileLstsarStore implements LstsarStoreInterface, JsonFileLstsarStoreInterface
{
    private string $rootDir;

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim(str_replace('\\', '/', $rootDir), '/');
        if (!is_dir($this->rootDir) && !mkdir($this->rootDir, 0775, true)) {
            throw new \RuntimeException('OPUS_LSTSAR_STORE_DIR_CREATE_FAILED: ' . $this->rootDir);
        }
    }

    /** @param array<string,mixed> $record */
    public function store(string $datasetId, array $record): string
    {
        $this->assertId($datasetId, 'OPUS_LSTSAR_DATASET_ID_INVALID');
        $recordId = hash('sha256', $datasetId . ':' . json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $dir = $this->datasetDir($datasetId) . '/records';
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new \RuntimeException('OPUS_LSTSAR_RECORD_DIR_CREATE_FAILED: ' . $dir);
        }

        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new \RuntimeException('OPUS_LSTSAR_RECORD_JSON_ENCODE_FAILED');
        }

        if (file_put_contents($dir . '/' . $recordId . '.json', $json . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException('OPUS_LSTSAR_RECORD_WRITE_FAILED: ' . $recordId);
        }

        return $recordId;
    }

    public function restore(string $datasetId, string $recordId): array
    {
        $this->assertId($datasetId, 'OPUS_LSTSAR_DATASET_ID_INVALID');
        $this->assertId($recordId, 'OPUS_LSTSAR_RECORD_ID_INVALID');

        $path = $this->datasetDir($datasetId) . '/records/' . $recordId . '.json';
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_LSTSAR_RECORD_NOT_FOUND: ' . $datasetId . ':' . $recordId);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OPUS_LSTSAR_RECORD_JSON_INVALID: ' . $recordId);
        }

        $this->audit($datasetId, [
            'stage' => 'restore',
            'code' => 'OPUS_LSTSAR_RESTORE_OK',
            'record_id' => $recordId,
        ]);

        return $decoded;
    }

    public function audit(string $datasetId, array $event): void
    {
        $this->assertId($datasetId, 'OPUS_LSTSAR_DATASET_ID_INVALID');
        $dir = $this->datasetDir($datasetId);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new \RuntimeException('OPUS_LSTSAR_AUDIT_DIR_CREATE_FAILED: ' . $dir);
        }

        $event['dataset_id'] = $datasetId;
        $event['index'] = count($this->auditTrail($datasetId));
        $event['time'] = gmdate('c');

        $json = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new \RuntimeException('OPUS_LSTSAR_AUDIT_JSON_ENCODE_FAILED');
        }

        if (file_put_contents($dir . '/audit.jsonl', $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('OPUS_LSTSAR_AUDIT_WRITE_FAILED: ' . $datasetId);
        }
    }

    public function auditTrail(string $datasetId): array
    {
        $this->assertId($datasetId, 'OPUS_LSTSAR_DATASET_ID_INVALID');
        $path = $this->datasetDir($datasetId) . '/audit.jsonl';
        if (!is_file($path)) {
            return [];
        }

        $events = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return $events;
    }

    private function datasetDir(string $datasetId): string
    {
        return $this->rootDir . '/' . $datasetId;
    }

    private function assertId(string $id, string $code): void
    {
        if (!preg_match('/^[a-z0-9_\-]{1,80}$/', $id)) {
            throw new \InvalidArgumentException($code . ': ' . $id);
        }
    }
}
