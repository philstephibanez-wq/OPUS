<?php
declare(strict_types=1);

use Opus\Log\Logger;
use Opus\Profiler\Profiler;

/** Singleton composition root shared by the isolated OWASYS front/back processes. */
final class OwasysApplication
{
    public const CONTRACT = 'OWASYS_APPLICATION_SINGLETON_V3';

    private static ?self $instance = null;
    private readonly Logger $logger;
    private readonly Profiler $profiler;

    private function __construct(
        private readonly string $siteRoot,
        private readonly OwasysProcessRuntimeInterface $runtime
    ) {
        $mode = $runtime->mode();
        if (!in_array($mode, ['front', 'back'], true)) {
            throw new RuntimeException('OWASYS_RUNTIME_MODE_INVALID');
        }

        $this->logger = new Logger(
            $siteRoot . '/var/logs',
            $mode === 'front'
                ? 'owasys-frontend.log'
                : 'rcp-backend.log'
        );
        $this->profiler = new Profiler(
            $siteRoot . '/var/profiler/' . $mode
        );
    }

    public static function instance(
        string $siteRoot,
        OwasysProcessRuntimeInterface $runtime
    ): self {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot
                || self::$instance->runtime->mode() !== $runtime->mode()) {
                throw new RuntimeException(
                    'OWASYS_APPLICATION_SINGLETON_CONTEXT_MISMATCH'
                );
            }
            return self::$instance;
        }

        return self::$instance = new self($siteRoot, $runtime);
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException(
            'OWASYS_APPLICATION_SINGLETON_UNSERIALIZE_FORBIDDEN'
        );
    }

    public function run(): void
    {
        $trace = $this->profiler->start();
        $traceId = $trace->getTraceId();
        $startedAt = microtime(true);
        $status = 'failed';
        $context = $this->requestContext();

        try {
            $this->logger->info(
                'owasys.runtime.' . $this->runtime->mode(),
                'request.received',
                $context,
                $traceId
            );
            $this->profiler->event(
                'owasys.runtime.' . $this->runtime->mode(),
                'request.received',
                $context
            );

            $this->runtime->run();
            $status = 'completed';
            $completed = [
                'runtime_mode' => $this->runtime->mode(),
                'duration_ms' => round(
                    (microtime(true) - $startedAt) * 1000,
                    3
                ),
            ];
            $this->logger->info(
                'owasys.runtime.' . $this->runtime->mode(),
                'request.completed',
                $completed,
                $traceId
            );
            $this->profiler->event(
                'owasys.runtime.' . $this->runtime->mode(),
                'request.completed',
                $completed
            );
        } catch (Throwable $error) {
            $failed = [
                'runtime_mode' => $this->runtime->mode(),
                'duration_ms' => round(
                    (microtime(true) - $startedAt) * 1000,
                    3
                ),
                'error_code' => $this->safeErrorCode($error),
                'exception_class' => $error::class,
                'exception_file' => $error->getFile(),
                'exception_line' => $error->getLine(),
            ];
            $this->logger->error(
                'owasys.runtime.' . $this->runtime->mode(),
                'request.failed',
                $failed,
                $traceId
            );
            $this->profiler->event(
                'owasys.runtime.' . $this->runtime->mode(),
                'request.failed',
                $failed
            );
            if (!headers_sent()) {
                header('X-Opus-Trace-Id: ' . $traceId);
            }
            $this->runtime->fail($error, $traceId);
        } finally {
            $this->profiler->stop([
                'component' => self::class,
                'status' => $status,
                'runtime_mode' => $this->runtime->mode(),
                'duration_ms' => round(
                    (microtime(true) - $startedAt) * 1000,
                    3
                ),
            ]);
        }
    }

    /** @return array{runtime_mode:string,method:string,path:string} */
    private function requestContext(): array
    {
        $path = parse_url(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        );
        $path = is_string($path) ? $path : '/';
        return [
            'runtime_mode' => $this->runtime->mode(),
            'method' => strtoupper((string) (
                $_SERVER['REQUEST_METHOD'] ?? 'GET'
            )),
            'path' => substr($path, 0, 512),
        ];
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OWASYS_RUNTIME_FAILED';
    }
}
