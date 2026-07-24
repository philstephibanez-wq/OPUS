<?php
declare(strict_types=1);

namespace Opus\Rcp\Composer;

use Opus\File\Json;
use Opus\Log\LoggerInterface;
use Opus\Profiler\ProfilerInterface;
use Opus\Profiler\TraceInterface;

/** Executes one allow-listed public Composer script and returns its JSON result. */
final class ComposerCommandExecutor implements ComposerCommandExecutorInterface
{
    private const DIAGNOSTIC_EXCERPT_BYTES = 8192;

    /** @param list<string> $composerCommand */
    public function __construct(
        private readonly string $opusRoot,
        private readonly array $composerCommand,
        private readonly int $timeoutSeconds,
        private readonly int $maxOutputBytes,
        private readonly LoggerInterface $logger,
        private readonly ProfilerInterface $profiler
    ) {
        if ($this->composerCommand === []
            || array_filter($this->composerCommand, 'is_string') !== $this->composerCommand) {
            throw new \RuntimeException('OPUS_RCP_COMPOSER_COMMAND_INVALID');
        }
        if ($this->timeoutSeconds < 1 || $this->timeoutSeconds > 600) {
            throw new \RuntimeException('OPUS_RCP_TIMEOUT_INVALID');
        }
        if ($this->maxOutputBytes < 4096 || $this->maxOutputBytes > 16777216) {
            throw new \RuntimeException('OPUS_RCP_OUTPUT_LIMIT_INVALID');
        }
    }

    public function execute(array $entry, array $request): array
    {
        $script = trim((string) ($entry['composer_script'] ?? ''));
        $argv = is_array($entry['argv'] ?? null)
            ? array_values(array_filter($entry['argv'], 'is_string'))
            : [];
        if ($script === '' || preg_match('/^[a-z0-9][a-z0-9:_-]*$/', $script) !== 1) {
            throw new \RuntimeException('OPUS_RCP_COMPOSER_SCRIPT_INVALID');
        }

        [$trace, $ownsTrace] = $this->trace($request);
        $traceId = $trace->getTraceId();
        $startedAt = microtime(true);
        $stdout = '';
        $stderr = '';
        $observedExitCode = -1;
        $closedExitCode = -1;
        $process = null;
        $pipes = [];

        $command = [
            ...$this->composerCommand,
            '--working-dir=' . $this->opusRoot,
            '--no-interaction',
            '--no-plugins',
            '--no-ansi',
            $script,
            '--',
            ...$argv,
            '--format=json',
        ];

        $this->logger->info('rcp.composer', 'command.started', [
            'script' => $script,
            'argv_count' => count($argv),
            'execution_id' => $this->safeExecutionId($request),
        ], $traceId);
        $this->profiler->event('rcp.composer', 'command.started', [
            'script' => $script,
            'argv_count' => count($argv),
        ]);

        try {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open(
                $command,
                $descriptors,
                $pipes,
                $this->opusRoot,
                null,
                ['bypass_shell' => true, 'suppress_errors' => true]
            );
            if (!is_resource($process)) {
                throw new \RuntimeException(
                    'OPUS_RCP_COMPOSER_PROCESS_START_FAILED'
                );
            }

            $encoded = Json::instance()->encode($request, false);
            if (fwrite($pipes[0], $encoded) === false) {
                throw new \RuntimeException('OPUS_RCP_STDIN_WRITE_FAILED');
            }
            fclose($pipes[0]);
            unset($encoded, $request);

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            [$stdout, $stderr, $observedExitCode] = $this->collect(
                $process,
                $pipes[1],
                $pipes[2]
            );
        } catch (\Throwable $error) {
            if (is_resource($process)) {
                @proc_terminate($process);
            }
            $this->recordFailure(
                $script,
                $traceId,
                $error,
                $stdout,
                $stderr,
                $observedExitCode,
                $closedExitCode,
                $startedAt
            );
            if ($ownsTrace) {
                $this->profiler->stop([
                    'component' => self::class,
                    'script' => $script,
                    'status' => 'failed',
                ]);
            }
            throw $error;
        } finally {
            foreach ([0, 1, 2] as $index) {
                if (isset($pipes[$index]) && is_resource($pipes[$index])) {
                    fclose($pipes[$index]);
                }
            }
            if (is_resource($process)) {
                $closedExitCode = proc_close($process);
            }
        }

        try {
            $exitCode = $this->resolveExitCode(
                $observedExitCode,
                $closedExitCode
            );
            $result = $this->parseResult($stdout, $stderr, $exitCode);
            $status = trim((string) ($result['status'] ?? ''));
            if ($exitCode !== 0 || $status !== 'succeeded') {
                $code = trim((string) (
                    $result['error_code']
                    ?? 'OPUS_RCP_COMPOSER_COMMAND_FAILED'
                ));
                throw new \RuntimeException(
                    preg_match('/^[A-Z0-9_:-]{3,240}$/', $code) === 1
                        ? $code
                        : 'OPUS_RCP_COMPOSER_COMMAND_FAILED'
                );
            }

            $durationMs = round((microtime(true) - $startedAt) * 1000, 3);
            $this->logger->info('rcp.composer', 'command.succeeded', [
                'script' => $script,
                'exit_code' => $exitCode,
                'duration_ms' => $durationMs,
                'stdout_bytes' => strlen($stdout),
                'stderr_bytes' => strlen($stderr),
            ], $traceId);
            $this->profiler->event('rcp.composer', 'command.succeeded', [
                'script' => $script,
                'exit_code' => $exitCode,
                'duration_ms' => $durationMs,
                'stdout_bytes' => strlen($stdout),
                'stderr_bytes' => strlen($stderr),
            ]);

            return $result;
        } catch (\Throwable $error) {
            $this->recordFailure(
                $script,
                $traceId,
                $error,
                $stdout,
                $stderr,
                $observedExitCode,
                $closedExitCode,
                $startedAt
            );
            throw $error;
        } finally {
            unset($stdout, $stderr);
            if ($ownsTrace) {
                $this->profiler->stop([
                    'component' => self::class,
                    'script' => $script,
                ]);
            }
        }
    }

    /** @return array{0:string,1:string,2:int} */
    private function collect($process, $stdoutPipe, $stderrPipe): array
    {
        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $this->timeoutSeconds;
        $exitCode = -1;

        while (true) {
            $out = stream_get_contents($stdoutPipe);
            $err = stream_get_contents($stderrPipe);
            $stdout .= is_string($out) ? $out : '';
            $stderr .= is_string($err) ? $err : '';
            if (strlen($stdout) + strlen($stderr) > $this->maxOutputBytes) {
                proc_terminate($process);
                throw new \RuntimeException('OPUS_RCP_OUTPUT_LIMIT_EXCEEDED');
            }

            $status = proc_get_status($process);
            if (!is_array($status)) {
                throw new \RuntimeException('OPUS_RCP_PROCESS_STATUS_FAILED');
            }
            if (($status['running'] ?? false) !== true) {
                $exitCode = (int) ($status['exitcode'] ?? -1);
                $out = stream_get_contents($stdoutPipe);
                $err = stream_get_contents($stderrPipe);
                $stdout .= is_string($out) ? $out : '';
                $stderr .= is_string($err) ? $err : '';
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process);
                throw new \RuntimeException(
                    'OPUS_RCP_COMPOSER_COMMAND_TIMEOUT'
                );
            }
            usleep(20000);
        }

        return [$stdout, $stderr, $exitCode];
    }

    /** @return array<string,mixed> */
    private function parseResult(
        string $stdout,
        string $stderr,
        int $exitCode
    ): array {
        foreach (array_reverse($this->jsonObjects($stdout)) as $candidate) {
            try {
                $decoded = Json::instance()->parse(
                    $candidate,
                    'composer:stdout'
                );
            } catch (\Throwable) {
                continue;
            }
            if (in_array(
                $decoded['contract'] ?? null,
                ['OPUS_CONSOLE_COMMAND_RESULT_V1', 'OPUS_CONSOLE_ERROR_V1'],
                true
            )) {
                return $decoded;
            }
        }

        $stderrCode = $this->stderrErrorCode($stderr);
        if ($stderrCode !== null) {
            throw new \RuntimeException($stderrCode);
        }
        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'OPUS_RCP_COMPOSER_COMMAND_FAILED'
            );
        }

        throw new \RuntimeException('OPUS_RCP_COMPOSER_RESULT_MISSING');
    }

    /** @return list<string> */
    private function jsonObjects(string $output): array
    {
        $objects = [];
        $length = strlen($output);
        $start = null;
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($index = 0; $index < $length; ++$index) {
            $character = $output[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($character === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($character === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($character === '"') {
                if ($depth > 0) {
                    $inString = true;
                }
                continue;
            }
            if ($character === '{') {
                if ($depth === 0) {
                    $start = $index;
                }
                ++$depth;
                continue;
            }
            if ($character !== '}' || $depth === 0) {
                continue;
            }

            --$depth;
            if ($depth === 0 && is_int($start)) {
                $objects[] = substr(
                    $output,
                    $start,
                    $index - $start + 1
                );
                $start = null;
            }
        }

        return $objects;
    }

    private function stderrErrorCode(string $stderr): ?string
    {
        $lines = array_reverse(preg_split('/\R/', trim($stderr)) ?: []);
        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if (preg_match('/^[A-Z][A-Z0-9_:-]{2,239}$/', $candidate) === 1) {
                return $candidate;
            }
        }
        return null;
    }

    private function resolveExitCode(int $observed, int $closed): int
    {
        if ($closed >= 0) {
            return $closed;
        }
        if ($observed >= 0) {
            return $observed;
        }
        throw new \RuntimeException(
            'OPUS_RCP_PROCESS_EXIT_CODE_UNAVAILABLE'
        );
    }

    /** @return array{0:TraceInterface,1:bool} */
    private function trace(array $request): array
    {
        $active = $this->profiler->getActiveTrace();
        if ($active instanceof TraceInterface) {
            return [$active, false];
        }
        $executionId = $this->safeExecutionId($request);
        return [
            $this->profiler->start($executionId !== '' ? $executionId : null),
            true,
        ];
    }

    /** @param array<string,mixed> $request */
    private function safeExecutionId(array $request): string
    {
        $executionId = trim((string) ($request['execution_id'] ?? ''));
        return preg_match('/^[a-f0-9]{16,64}$/', $executionId) === 1
            ? $executionId
            : '';
    }

    private function recordFailure(
        string $script,
        string $traceId,
        \Throwable $error,
        string $stdout,
        string $stderr,
        int $observedExitCode,
        int $closedExitCode,
        float $startedAt
    ): void {
        $context = [
            'script' => $script,
            'error_code' => $this->safeErrorCode($error),
            'exception_class' => $error::class,
            'exception_file' => $error->getFile(),
            'exception_line' => $error->getLine(),
            'observed_exit_code' => $observedExitCode,
            'closed_exit_code' => $closedExitCode,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 3),
            'stdout_bytes' => strlen($stdout),
            'stderr_bytes' => strlen($stderr),
            'stdout_excerpt' => $this->diagnosticExcerpt($stdout),
            'stderr_excerpt' => $this->diagnosticExcerpt($stderr),
        ];
        $this->logger->error(
            'rcp.composer',
            'command.failed',
            $context,
            $traceId
        );
        $this->profiler->event(
            'rcp.composer',
            'command.failed',
            $context
        );
    }

    private function safeErrorCode(\Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OPUS_RCP_COMPOSER_COMMAND_FAILED';
    }

    private function diagnosticExcerpt(string $output): string
    {
        $output = preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/', '', $output)
            ?? '';
        $output = preg_replace(
            '/(?i)(authorization|password|pass|secret|token|api[_-]?key|hmac)(\s*[=:]\s*)([^\s,;]+)/',
            '$1$2[REDACTED]',
            $output
        ) ?? '';
        $output = preg_replace(
            '~(?i)("(?:authorization|password|pass|secret|token|api[_-]?key|hmac)"\s*:\s*)"(?:\\\\.|[^"\\\\])*"~',
            '$1"[REDACTED]"',
            $output
        ) ?? '';
        $output = preg_replace('/\b[a-f0-9]{32,}\b/i', '[REDACTED_HEX]', $output)
            ?? '';
        $output = trim($output);
        if (strlen($output) > self::DIAGNOSTIC_EXCERPT_BYTES) {
            $output = substr($output, 0, self::DIAGNOSTIC_EXCERPT_BYTES)
                . '...[TRUNCATED]';
        }
        return $output;
    }
}
