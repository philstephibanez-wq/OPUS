<?php
declare(strict_types=1);

namespace Opus\Profiler;

final class TraceFileRepository implements TraceFileRepositoryInterface
{
    private string $storageDir;

    public function __construct(string $storageDir)
    {
        $this->storageDir = rtrim(str_replace('\\', '/', $storageDir), '/');
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0775, true);
        }
    }

    public function listTraces(): array
    {
        $files = glob($this->storageDir . '/*.json') ?: [];
        rsort($files);
        $rows = [];
        foreach (array_slice($files, 0, 100) as $file) {
            $data = $this->readJsonFile($file);
            if (!$data) {
                continue;
            }
            $rows[] = [
                'trace_id' => (string)($data['trace_id'] ?? basename($file, '.json')),
                'started_at' => (string)($data['started_at'] ?? ''),
                'duration_ms' => (string)($data['duration_ms'] ?? ''),
                'event_count' => (string)($data['event_count'] ?? count((array)($data['events'] ?? []))),
                'status' => (string)(($data['summary']['status'] ?? 'ok')),
            ];
        }
        return $rows;
    }

    public function readTrace(string $traceId): array
    {
        if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $traceId)) {
            throw new \InvalidArgumentException('OPUS_PROFILER_TRACE_ID_INVALID: ' . $traceId);
        }
        $path = $this->storageDir . '/' . $traceId . '.json';
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_NOT_FOUND: ' . $traceId);
        }
        $data = $this->readJsonFile($path);
        if (!$data) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_INVALID: ' . $traceId);
        }
        return $data;
    }

    private function readJsonFile(string $file): array
    {
        $json = file_get_contents($file);
        if (!is_string($json)) {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}