<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\File\File;
use Opus\File\StructuredFileLoader;

/**
 * OPUS profiler with one append-only JSONL storage file per application.
 *
 * V2 records expose causal spans and typed statuses. The reader intentionally
 * remains compatible with existing V1 JSONL records.
 */
final class Profiler implements ProfilerInterface
{
    private const SITE_CONTRACT = 'OPUS_SITE_STANDARD_CONTRACT_CORE';
    private const TRACE_ID_PATTERN = '/^[a-f0-9]{16,64}$/D';

    private string $storageFile;
    private ?Trace $activeTrace = null;

    public function __construct(string $storagePath)
    {
        $this->storageFile = $this->resolveStorageFile($storagePath);
        $storageDirectory = dirname($this->storageFile);
        if (!is_dir($storageDirectory)
            && !mkdir($storageDirectory, 0775, true)
            && !is_dir($storageDirectory)
        ) {
            throw new \RuntimeException('OPUS_PROFILER_STORAGE_CREATE_FAILED:' . $storageDirectory);
        }
    }

    public function start(?string $traceId = null): Trace
    {
        if ($this->activeTrace !== null) {
            throw new \LogicException('OPUS_PROFILER_TRACE_ALREADY_STARTED');
        }
        $this->activeTrace = new Trace($traceId);
        $this->activeTrace->addEvent('profiler', 'trace.started');

        return $this->activeTrace;
    }

    public function getActiveTrace(): ?Trace
    {
        return $this->activeTrace;
    }

    /** @param array<string,mixed> $context */
    public function event(
        string $category,
        string $name,
        array $context = [],
        string $status = 'success',
        ?string $spanId = null,
        ?string $parentSpanId = null
    ): string {
        return $this->requireActiveTrace()->addEvent(
            $category,
            $name,
            $context,
            $status,
            $spanId,
            $parentSpanId
        );
    }

    /** @param array<string,mixed> $context */
    public function beginSpan(
        string $category,
        string $name,
        array $context = [],
        ?string $parentSpanId = null
    ): string {
        return $this->requireActiveTrace()->beginSpan(
            $category,
            $name,
            $context,
            $parentSpanId
        );
    }

    /** @param array<string,mixed> $context */
    public function endSpan(
        string $spanId,
        string $status = 'success',
        array $context = []
    ): void {
        $this->requireActiveTrace()->endSpan($spanId, $status, $context);
    }

    /** @param array<string,mixed> $summary */
    public function stop(array $summary = []): string
    {
        $trace = $this->requireActiveTrace();
        $trace->addEvent('profiler', 'trace.stopped');
        $trace->finish();
        $path = $this->writeTrace($trace, $summary);
        $this->activeTrace = null;

        return $path;
    }

    /** @param array<string,mixed> $summary */
    public function writeTrace(Trace $trace, array $summary = []): string
    {
        $record = $trace->toArray($summary);
        $record['record_id'] = bin2hex(random_bytes(8));
        $record['recorded_at'] = gmdate('c');
        try {
            $json = json_encode(
                $record,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $cause) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_JSON_ENCODE_FAILED', 0, $cause);
        }
        if (file_put_contents($this->storageFile, $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_WRITE_FAILED:' . $this->storageFile);
        }

        return $this->storageFile;
    }

    /** @return list<array<string,mixed>> */
    public function readTrace(string $traceId): array
    {
        $traceId = strtolower(trim($traceId));
        if (preg_match(self::TRACE_ID_PATTERN, $traceId) !== 1) {
            throw new \InvalidArgumentException('OPUS_PROFILER_TRACE_ID_INVALID');
        }
        if (!is_file($this->storageFile)) {
            throw new \RuntimeException('OPUS_PROFILER_STORAGE_FILE_MISSING:' . $this->storageFile);
        }
        $handle = fopen($this->storageFile, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_READ_FAILED:' . $this->storageFile);
        }

        $records = [];
        try {
            if (!flock($handle, LOCK_SH)) {
                throw new \RuntimeException('OPUS_PROFILER_TRACE_LOCK_FAILED:' . $this->storageFile);
            }
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                ++$lineNumber;
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                try {
                    $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $cause) {
                    throw new \RuntimeException(
                        'OPUS_PROFILER_RECORD_JSON_INVALID:' . $lineNumber,
                        0,
                        $cause
                    );
                }
                if (!is_array($record)) {
                    throw new \RuntimeException('OPUS_PROFILER_RECORD_INVALID:' . $lineNumber);
                }
                $schema = $record['schema'] ?? null;
                if (!in_array($schema, ['OPUS_PROFILER_TRACE_V1', 'OPUS_PROFILER_TRACE_V2'], true)) {
                    throw new \RuntimeException('OPUS_PROFILER_RECORD_SCHEMA_INVALID:' . $lineNumber);
                }
                if (($record['trace_id'] ?? null) === $traceId) {
                    $records[] = $record;
                }
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
        if ($records === []) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_NOT_FOUND:' . $traceId);
        }

        return $records;
    }

    /** @param list<array<string,mixed>> $records */
    public function importRecords(array $records, ?string $rootParentSpanId = null): void
    {
        $trace = $this->requireActiveTrace();
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new \InvalidArgumentException('OPUS_PROFILER_REMOTE_RECORD_INVALID');
            }
            $trace->importRecord($record, $rootParentSpanId);
        }
    }

    private function requireActiveTrace(): Trace
    {
        if ($this->activeTrace === null) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_NOT_STARTED');
        }

        return $this->activeTrace;
    }

    private function resolveStorageFile(string $storagePath): string
    {
        $storagePath = rtrim(str_replace('\\', '/', trim($storagePath)), '/');
        if ($storagePath === '') {
            throw new \InvalidArgumentException('OPUS_PROFILER_STORAGE_PATH_INVALID');
        }
        $searchRoot = str_ends_with(strtolower($storagePath), '.jsonl')
            ? dirname($storagePath)
            : $storagePath;
        $siteRoot = $this->findSiteRoot($searchRoot);
        if ($siteRoot !== null) {
            $siteConfigFile = $siteRoot . '/config/site.json';
            try {
                $site = StructuredFileLoader::instance()->read($siteConfigFile);
            } catch (\Throwable $cause) {
                throw new \RuntimeException(
                    'OPUS_PROFILER_SITE_CONFIG_INVALID:' . $siteConfigFile,
                    0,
                    $cause
                );
            }
            if (($site['contract'] ?? null) !== self::SITE_CONTRACT) {
                throw new \RuntimeException('OPUS_PROFILER_SITE_CONTRACT_INVALID:' . $siteRoot);
            }
            $siteId = trim((string) ($site['site_id'] ?? ''));
            if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $siteId) !== 1) {
                throw new \RuntimeException('OPUS_PROFILER_SITE_ID_INVALID:' . $siteRoot);
            }

            return $siteRoot . '/var/profiler/' . $siteId . '.jsonl';
        }
        if (str_ends_with(strtolower($storagePath), '.jsonl')) {
            return $storagePath;
        }

        return $storagePath . '/opus-profiler.jsonl';
    }

    private function findSiteRoot(string $start): ?string
    {
        $file = File::instance();
        $candidate = rtrim(str_replace('\\', '/', $start), '/');
        while ($candidate !== '') {
            if ($file->exists($candidate . '/config/site.json')) {
                return $candidate;
            }
            $parent = str_replace('\\', '/', dirname($candidate));
            if ($parent === $candidate || $parent === '.' || $parent === '') {
                break;
            }
            $candidate = rtrim($parent, '/');
        }

        return null;
    }
}
