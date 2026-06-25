<?php
declare(strict_types=1);

namespace Opus\Log;

/**
 * Minimal OPUS file logger foundation.
 *
 * Contract:
 * - no framework dependency;
 * - creates its log directory explicitly;
 * - writes one structured line per event;
 * - redacts common sensitive context keys;
 * - remains independent from legacy legacy debug.
 */
final class Logger
{
    private const LEVELS = ['debug', 'info', 'warning', 'error', 'critical'];

    private string $logFile;

    public function __construct(string $logDir, string $filename = 'opus.log')
    {
        $this->logFile = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($logDir) && !mkdir($logDir, 0775, true)) {
            throw new \RuntimeException('OPUS_LOG_DIR_CREATE_FAILED: ' . $logDir);
        }
    }

    /** @param array<string,mixed> $context */
    public function debug(string $channel, string $message, array $context = [], ?string $traceId = null): void
    {
        $this->write('debug', $channel, $message, $context, $traceId);
    }

    /** @param array<string,mixed> $context */
    public function info(string $channel, string $message, array $context = [], ?string $traceId = null): void
    {
        $this->write('info', $channel, $message, $context, $traceId);
    }

    /** @param array<string,mixed> $context */
    public function warning(string $channel, string $message, array $context = [], ?string $traceId = null): void
    {
        $this->write('warning', $channel, $message, $context, $traceId);
    }

    /** @param array<string,mixed> $context */
    public function error(string $channel, string $message, array $context = [], ?string $traceId = null): void
    {
        $this->write('error', $channel, $message, $context, $traceId);
    }

    /** @param array<string,mixed> $context */
    public function critical(string $channel, string $message, array $context = [], ?string $traceId = null): void
    {
        $this->write('critical', $channel, $message, $context, $traceId);
    }

    /** @param array<string,mixed> $context */
    private function write(string $level, string $channel, string $message, array $context, ?string $traceId): void
    {
        if (!in_array($level, self::LEVELS, true)) {
            throw new \InvalidArgumentException('OPUS_LOG_LEVEL_INVALID: ' . $level);
        }

        $entry = [
            'time' => gmdate('c'),
            'level' => $level,
            'channel' => $channel,
            'trace_id' => $traceId,
            'message' => $message,
            'context' => $this->redactContext($context),
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            throw new \RuntimeException('OPUS_LOG_JSON_ENCODE_FAILED');
        }

        if (file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('OPUS_LOG_WRITE_FAILED: ' . $this->logFile);
        }
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function redactContext(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);

            if (in_array($normalized, ['password', 'pass', 'secret', 'token', 'api_key', 'apikey', 'authorization'], true)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }
}
