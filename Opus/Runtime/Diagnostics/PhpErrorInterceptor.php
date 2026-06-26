<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

final class PhpErrorInterceptor implements PhpErrorInterceptorInterface
{
    private static bool $registered = false;
    private static string $rootDir = '';

    public static function register(string $rootDir): void
    {
        if (self::$registered) {
            return;
        }
        self::$rootDir = rtrim(str_replace('\\', '/', $rootDir), '/');
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
        self::$registered = true;
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }
        throw PhpErrorException::fromPhpError($severity, $message, $file, $line);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
            return;
        }
        $dir = self::$rootDir . '/var/profiler';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $traceId = 'fatal-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $payload = [
            'schema' => 'OPUS_PROFILER_TRACE_V1',
            'trace_id' => $traceId,
            'started_at' => gmdate('c'),
            'duration_ms' => 0,
            'summary' => ['status' => 'fatal'],
            'event_count' => 1,
            'events' => [[
                'index' => 1,
                'time' => gmdate('c'),
                'elapsed_ms' => 0,
                'category' => 'exception',
                'name' => 'php.fatal',
                'memory' => [
                    'usage_bytes' => memory_get_usage(true),
                    'peak_bytes' => memory_get_peak_usage(true),
                ],
                'context' => [
                    'type' => $error['type'] ?? null,
                    'message' => $error['message'] ?? '',
                    'file' => $error['file'] ?? '',
                    'line' => $error['line'] ?? 0,
                ],
            ]],
        ];
        @file_put_contents($dir . '/' . $traceId . '.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, LOCK_EX);
    }
}