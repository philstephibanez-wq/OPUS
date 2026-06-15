<?php

declare(strict_types=1);

namespace Opus\Log;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: LOG
 *   role: RuntimeLogger writes official OPUS runtime logs.
 *   contract:
 *     - writes only below OPUS var/logs
 *     - fails explicitly when the log directory cannot be created or written
 *     - never falls back to stdout, temp folders, or workspace paths
 *   examples:
 *     - runtime-log-overview
 *   diagrams:
 *     - runtime-log-flow
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Write official OPUS runtime log events.
 *
 * Responsibility:
 *   Provide a minimal deterministic logger for boot, autoload cache rebuild and
 *   runtime contract diagnostics.
 *
 * Contract:
 *   OPUS product runtime logs are written only in OPUS/var/logs. A write failure
 *   is a runtime error, not a silent fallback.
 */
final class RuntimeLogger
{
    private readonly string $logFile;

    public function __construct(private readonly string $projectRoot, ?string $logFile = null)
    {
        $this->logFile = $logFile ?? self::defaultLogFile($projectRoot);
    }

    public static function defaultLogFile(string $projectRoot): string
    {
        return rtrim($projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'var'
            . DIRECTORY_SEPARATOR . 'logs'
            . DIRECTORY_SEPARATOR . 'opus_runtime.log';
    }

    /**
     * PUBLIC API
     *
     * @param array<string,mixed> $context
     */
    public function info(string $event, array $context = []): void
    {
        $this->write('INFO', $event, $context);
    }

    /**
     * PUBLIC API
     *
     * @param array<string,mixed> $context
     */
    public function error(string $event, array $context = []): void
    {
        $this->write('ERROR', $event, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function write(string $level, string $event, array $context): void
    {
        $event = trim($event);
        if ($event === '') {
            throw new RuntimeException('OPUS_RUNTIME_LOG_EVENT_EMPTY');
        }

        $dir = dirname($this->logFile);
        $this->assertInsideLogsDirectory($dir);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('OPUS_RUNTIME_LOG_DIR_CREATE_FAILED: ' . $dir);
        }

        $record = [
            'ts' => date('c'),
            'level' => $level,
            'event' => $event,
            'context' => $context,
        ];

        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($line)) {
            throw new RuntimeException('OPUS_RUNTIME_LOG_JSON_ENCODE_FAILED: ' . $event);
        }

        if (file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('OPUS_RUNTIME_LOG_WRITE_FAILED: ' . $this->logFile);
        }
    }

    private function assertInsideLogsDirectory(string $dir): void
    {
        $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/');
        $logsRoot = $root . '/var/logs';
        $normalizedDir = rtrim(str_replace('\\', '/', $dir), '/');

        if ($normalizedDir !== $logsRoot && !str_starts_with($normalizedDir . '/', $logsRoot . '/')) {
            throw new RuntimeException('OPUS_RUNTIME_LOG_OUTSIDE_VAR_LOGS: ' . $dir);
        }
    }
}
