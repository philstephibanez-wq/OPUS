<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\File\File;
use Opus\File\StructuredFileLoader;

/**
 * OPUS profiler with one append-only JSONL storage file per application.
 *
 * Contract:
 * - dev-only diagnostic storage;
 * - one canonical profiler file per OPUS application;
 * - one JSON object per completed profiler record;
 * - multiple records may share one trace_id across distributed components;
 * - explicit trace_id propagation;
 * - trace lookup remains available for URL-driven profiler readers;
 * - explicit failure when storage cannot be created, read or written.
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

        if (
            !is_dir($storageDirectory)
            && !mkdir($storageDirectory, 0775, true)
            && !is_dir($storageDirectory)
        ) {
            throw new \RuntimeException(
                'OPUS_PROFILER_STORAGE_CREATE_FAILED:' . $storageDirectory
            );
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
    public function event(
        string $category,
        string $name,
        array $context = []
    ): void {
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
        $record = $trace->toArray($summary);
        $record['record_id'] = bin2hex(random_bytes(8));
        $record['recorded_at'] = gmdate('c');

        try {
            $json = json_encode(
                $record,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $cause) {
            throw new \RuntimeException(
                'OPUS_PROFILER_TRACE_JSON_ENCODE_FAILED',
                0,
                $cause
            );
        }

        if (file_put_contents(
            $this->storageFile,
            $json . PHP_EOL,
            FILE_APPEND | LOCK_EX
        ) === false) {
            throw new \RuntimeException(
                'OPUS_PROFILER_TRACE_WRITE_FAILED:' . $this->storageFile
            );
        }

        return $this->storageFile;
    }

    /** @return list<array<string,mixed>> */
    public function readTrace(string $traceId): array
    {
        $traceId = strtolower(trim($traceId));
        if (preg_match(self::TRACE_ID_PATTERN, $traceId) !== 1) {
            throw new \InvalidArgumentException(
                'OPUS_PROFILER_TRACE_ID_INVALID'
            );
        }
        if (!is_file($this->storageFile)) {
            throw new \RuntimeException(
                'OPUS_PROFILER_STORAGE_FILE_MISSING:' . $this->storageFile
            );
        }

        $handle = fopen($this->storageFile, 'rb');
        if ($handle === false) {
            throw new \RuntimeException(
                'OPUS_PROFILER_TRACE_READ_FAILED:' . $this->storageFile
            );
        }

        $records = [];
        try {
            if (!flock($handle, LOCK_SH)) {
                throw new \RuntimeException(
                    'OPUS_PROFILER_TRACE_LOCK_FAILED:' . $this->storageFile
                );
            }

            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                ++$lineNumber;
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                try {
                    $record = json_decode(
                        $line,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                } catch (\JsonException $cause) {
                    throw new \RuntimeException(
                        'OPUS_PROFILER_RECORD_JSON_INVALID:' . $lineNumber,
                        0,
                        $cause
                    );
                }

                if (!is_array($record)) {
                    throw new \RuntimeException(
                        'OPUS_PROFILER_RECORD_INVALID:' . $lineNumber
                    );
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
            throw new \RuntimeException(
                'OPUS_PROFILER_TRACE_NOT_FOUND:' . $traceId
            );
        }

        return $records;
    }

    private function resolveStorageFile(string $storagePath): string
    {
        $storagePath = rtrim(
            str_replace('\\', '/', trim($storagePath)),
            '/'
        );
        if ($storagePath === '') {
            throw new \InvalidArgumentException(
                'OPUS_PROFILER_STORAGE_PATH_INVALID'
            );
        }

        $searchRoot = str_ends_with(strtolower($storagePath), '.jsonl')
            ? dirname($storagePath)
            : $storagePath;
        $siteRoot = $this->findSiteRoot($searchRoot);

        if ($siteRoot !== null) {
            $siteConfigFile = $siteRoot . '/config/site.json';
            try {
                $site = StructuredFileLoader::instance()->read(
                    $siteConfigFile
                );
            } catch (\Throwable $cause) {
                throw new \RuntimeException(
                    'OPUS_PROFILER_SITE_CONFIG_INVALID:' . $siteConfigFile,
                    0,
                    $cause
                );
            }
            if (($site['contract'] ?? null) !== self::SITE_CONTRACT) {
                throw new \RuntimeException(
                    'OPUS_PROFILER_SITE_CONTRACT_INVALID:' . $siteRoot
                );
            }

            $siteId = trim((string) ($site['site_id'] ?? ''));
            if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $siteId) !== 1) {
                throw new \RuntimeException(
                    'OPUS_PROFILER_SITE_ID_INVALID:' . $siteRoot
                );
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
