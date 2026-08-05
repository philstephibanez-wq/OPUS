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
    private const DEFAULT_MAX_BYTES = 10485760;
    private const DEFAULT_MAX_ARCHIVES = 5;
    private const MIN_MAX_BYTES = 65536;
    private const MAX_MAX_BYTES = 1073741824;
    private const MAX_ARCHIVES = 100;

    private string $storageFile;
    private ?Trace $activeTrace = null;
    private ProfilerContextSanitizerInterface $contextSanitizer;
    private int $maxBytes = self::DEFAULT_MAX_BYTES;
    private int $maxArchives = self::DEFAULT_MAX_ARCHIVES;

    public function __construct(string $storagePath)
    {
        $this->contextSanitizer = new ProfilerContextSanitizer();
        $this->storageFile = $this->resolveStorageFile($storagePath);
        $this->loadRetentionPolicy();
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
            (array) $this->contextSanitizer->sanitize($context),
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
            (array) $this->contextSanitizer->sanitize($context),
            $parentSpanId
        );
    }

    /** @param array<string,mixed> $context */
    public function endSpan(
        string $spanId,
        string $status = 'success',
        array $context = []
    ): void {
        $this->requireActiveTrace()->endSpan(
            $spanId,
            $status,
            (array) $this->contextSanitizer->sanitize($context)
        );
    }

    /** @param array<string,mixed> $summary */
    public function stop(array $summary = []): string
    {
        $trace = $this->requireActiveTrace();
        $trace->addEvent('profiler', 'trace.stopped');
        $trace->finish();
        $path = $this->writeTrace(
            $trace,
            (array) $this->contextSanitizer->sanitize($summary)
        );
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
        $this->appendRecord($json . PHP_EOL);

        return $this->storageFile;
    }

    /** @return list<array<string,mixed>> */
    public function readTrace(string $traceId): array
    {
        $traceId = strtolower(trim($traceId));
        if (preg_match(self::TRACE_ID_PATTERN, $traceId) !== 1) {
            throw new \InvalidArgumentException('OPUS_PROFILER_TRACE_ID_INVALID');
        }
        $storageFiles = $this->retainedStorageFiles();
        if ($storageFiles === []) {
            throw new \RuntimeException('OPUS_PROFILER_STORAGE_FILE_MISSING:' . $this->storageFile);
        }
        $lockFile = $this->storageFile . '.lock';
        $lock = fopen($lockFile, 'c+b');
        if ($lock === false) {
            throw new \RuntimeException('OPUS_PROFILER_RETENTION_LOCK_OPEN_FAILED:' . $lockFile);
        }

        $records = [];
        try {
            if (!flock($lock, LOCK_SH)) {
                throw new \RuntimeException('OPUS_PROFILER_TRACE_LOCK_FAILED:' . $lockFile);
            }
            foreach ($storageFiles as $storageFile) {
                $handle = fopen($storageFile, 'rb');
                if ($handle === false) {
                    throw new \RuntimeException('OPUS_PROFILER_TRACE_READ_FAILED:' . $storageFile);
                }
                try {
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
                                'OPUS_PROFILER_RECORD_JSON_INVALID:' . $storageFile . ':' . $lineNumber,
                                0,
                                $cause
                            );
                        }
                        if (!is_array($record)) {
                            throw new \RuntimeException(
                                'OPUS_PROFILER_RECORD_INVALID:' . $storageFile . ':' . $lineNumber
                            );
                        }
                        $schema = $record['schema'] ?? null;
                        if (!in_array($schema, ['OPUS_PROFILER_TRACE_V1', 'OPUS_PROFILER_TRACE_V2'], true)) {
                            throw new \RuntimeException(
                                'OPUS_PROFILER_RECORD_SCHEMA_INVALID:' . $storageFile . ':' . $lineNumber
                            );
                        }
                        if (($record['trace_id'] ?? null) === $traceId) {
                            $records[] = $record;
                        }
                    }
                } finally {
                    fclose($handle);
                }
            }
            flock($lock, LOCK_UN);
        } finally {
            fclose($lock);
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

    private function appendRecord(string $record): void
    {
        if (strlen($record) > $this->maxBytes) {
            throw new \RuntimeException('OPUS_PROFILER_RECORD_EXCEEDS_RETENTION_LIMIT');
        }
        $lockFile = $this->storageFile . '.lock';
        $lock = fopen($lockFile, 'c+b');
        if ($lock === false) {
            throw new \RuntimeException('OPUS_PROFILER_RETENTION_LOCK_OPEN_FAILED:' . $lockFile);
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('OPUS_PROFILER_RETENTION_LOCK_FAILED:' . $lockFile);
            }
            clearstatcache(true, $this->storageFile);
            $currentBytes = is_file($this->storageFile) ? filesize($this->storageFile) : 0;
            if ($currentBytes === false) {
                throw new \RuntimeException('OPUS_PROFILER_STORAGE_STAT_FAILED:' . $this->storageFile);
            }
            if ($currentBytes > 0 && $currentBytes + strlen($record) > $this->maxBytes) {
                $this->rotateStorage();
            }
            if (file_put_contents($this->storageFile, $record, FILE_APPEND) === false) {
                throw new \RuntimeException('OPUS_PROFILER_TRACE_WRITE_FAILED:' . $this->storageFile);
            }
            fflush($lock);
            flock($lock, LOCK_UN);
        } finally {
            fclose($lock);
        }
    }

    /** @return list<string> */
    private function retainedStorageFiles(): array
    {
        $files = [];
        for ($index = $this->maxArchives; $index >= 1; --$index) {
            $archive = $this->storageFile . '.' . $index;
            if (is_file($archive)) {
                $files[] = $archive;
            }
        }
        if (is_file($this->storageFile)) {
            $files[] = $this->storageFile;
        }

        return $files;
    }

    private function rotateStorage(): void
    {
        if ($this->maxArchives === 0) {
            if (is_file($this->storageFile) && !unlink($this->storageFile)) {
                throw new \RuntimeException('OPUS_PROFILER_STORAGE_TRUNCATE_FAILED:' . $this->storageFile);
            }
            return;
        }
        $oldest = $this->storageFile . '.' . $this->maxArchives;
        if (is_file($oldest) && !unlink($oldest)) {
            throw new \RuntimeException('OPUS_PROFILER_ARCHIVE_DELETE_FAILED:' . $oldest);
        }
        for ($index = $this->maxArchives - 1; $index >= 1; --$index) {
            $source = $this->storageFile . '.' . $index;
            if (!is_file($source)) {
                continue;
            }
            $target = $this->storageFile . '.' . ($index + 1);
            if (!rename($source, $target)) {
                throw new \RuntimeException('OPUS_PROFILER_ARCHIVE_ROTATE_FAILED:' . $source);
            }
        }
        if (is_file($this->storageFile)
            && !rename($this->storageFile, $this->storageFile . '.1')
        ) {
            throw new \RuntimeException('OPUS_PROFILER_STORAGE_ROTATE_FAILED:' . $this->storageFile);
        }
    }

    private function loadRetentionPolicy(): void
    {
        $siteRoot = $this->findSiteRoot(dirname($this->storageFile));
        if ($siteRoot === null) {
            return;
        }
        $siteConfigFile = $siteRoot . '/config/site.json';
        try {
            $site = StructuredFileLoader::instance()->read($siteConfigFile);
        } catch (\Throwable $cause) {
            throw new \RuntimeException(
                'OPUS_PROFILER_RETENTION_CONFIG_INVALID:' . $siteConfigFile,
                0,
                $cause
            );
        }
        $profiler = $site['diagnostics']['profiler'] ?? null;
        $retention = is_array($profiler) ? ($profiler['retention'] ?? null) : null;
        if ($retention === null) {
            return;
        }
        if (!is_array($retention)) {
            throw new \RuntimeException('OPUS_PROFILER_RETENTION_POLICY_INVALID:' . $siteConfigFile);
        }
        $maxBytes = filter_var($retention['max_bytes'] ?? null, FILTER_VALIDATE_INT);
        $maxArchives = filter_var($retention['max_archives'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($maxBytes)
            || $maxBytes < self::MIN_MAX_BYTES
            || $maxBytes > self::MAX_MAX_BYTES
        ) {
            throw new \RuntimeException('OPUS_PROFILER_RETENTION_MAX_BYTES_INVALID:' . $siteConfigFile);
        }
        if (!is_int($maxArchives)
            || $maxArchives < 0
            || $maxArchives > self::MAX_ARCHIVES
        ) {
            throw new \RuntimeException('OPUS_PROFILER_RETENTION_MAX_ARCHIVES_INVALID:' . $siteConfigFile);
        }
        $this->maxBytes = $maxBytes;
        $this->maxArchives = $maxArchives;
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
