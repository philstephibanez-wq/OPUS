<?php
declare(strict_types=1);

namespace Opus\Profiler;

/**
 * Minimal OPUS profiler foundation.
 *
 * Contract:
 * - dev-only diagnostic storage;
 * - no runtime dependency on legacy debug;
 * - one JSON file per trace;
 * - explicit trace_id propagation;
 * - explicit failure when storage cannot be created or written.
 */
final class Profiler
 implements ProfilerInterface {
    private string $storageDir;
    private ?Trace $activeTrace = null;

    public function __construct(string $storageDir)
    {
        $this->storageDir = rtrim($storageDir, DIRECTORY_SEPARATOR);

        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0775, true)) {
            throw new \RuntimeException('OPUS_PROFILER_STORAGE_CREATE_FAILED: ' . $this->storageDir);
        }
    }

    public function start(?string $traceId = null): Trace
    {
        $this->activeTrace = new Trace($traceId);
        $this->activeTrace->addEvent('profiler', 'trace.started');

        return $this->activeTrace;
    }

    public function getActiveTrace(): ?Trace
    {
        return $this->activeTrace;
    }

    /** @param array<string,mixed> $context */
    public function event(string $category, string $name, array $context = []): void
    {
        if ($this->activeTrace === null) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_NOT_STARTED');
        }

        $this->activeTrace->addEvent($category, $name, $context);
    }

    /** @param array<string,mixed> $summary */
    public function stop(array $summary = []): string
    {
        if ($this->activeTrace === null) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_NOT_STARTED');
        }

        $this->activeTrace->addEvent('profiler', 'trace.stopped');
        $this->activeTrace->finish();

        $path = $this->writeTrace($this->activeTrace, $summary);
        $this->activeTrace = null;

        return $path;
    }

    /** @param array<string,mixed> $summary */
    public function writeTrace(Trace $trace, array $summary = []): string
    {
        $path = $this->storageDir . DIRECTORY_SEPARATOR . $trace->getTraceId() . '.json';

        $json = json_encode($trace->toArray($summary), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_JSON_ENCODE_FAILED');
        }

        if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_WRITE_FAILED: ' . $path);
        }

        return $path;
    }
}
