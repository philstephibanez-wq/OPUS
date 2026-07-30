<?php
declare(strict_types=1);

use Opus\Application\Runtime\GeneratedSiteRuntime;
use Opus\Http\Response;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;

final class OpusDemoApplication
{
    private static ?self $instance = null;
    private readonly GeneratedSiteRuntime $runtime;
    private readonly Logger $logger;
    private readonly Profiler $profiler;

    private function __construct(private readonly string $siteRoot)
    {
        $this->runtime = new GeneratedSiteRuntime($siteRoot);
        $this->logger = new Logger(
            $siteRoot . '/var/logs',
            'application.log'
        );
        $this->profiler = new Profiler($siteRoot . '/var/profiler');
    }

    public static function instance(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException(
                    'OPUS_APPLICATION_SINGLETON_ROOT_MISMATCH'
                );
            }
            return self::$instance;
        }
        return self::$instance = new self($siteRoot);
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException(
            'OPUS_APPLICATION_SINGLETON_UNSERIALIZE_FORBIDDEN'
        );
    }

    public function handle(): Response
    {
        $trace = $this->profiler->start();
        $traceId = $trace->getTraceId();
        $startedAt = microtime(true);
        $status = 'failed';

        try {
            $context = ['method' => $this->requestMethod()];
            $this->logger->info(
                'application.runtime',
                'request.received',
                $context,
                $traceId
            );
            $this->profiler->event(
                'application.runtime',
                'request.received',
                $context
            );

            $response = $this->runtime->handle();
            $status = 'completed';
            $durationMs = round(
                (microtime(true) - $startedAt) * 1000,
                3
            );
            $completed = ['duration_ms' => $durationMs];
            $this->logger->info(
                'application.runtime',
                'request.completed',
                $completed,
                $traceId
            );
            $this->profiler->event(
                'application.runtime',
                'request.completed',
                $completed
            );

            return $response;
        } catch (Throwable $error) {
            $durationMs = round(
                (microtime(true) - $startedAt) * 1000,
                3
            );
            $failed = [
                'duration_ms' => $durationMs,
                'error_code' => $this->safeErrorCode($error),
            ];
            $this->logger->error(
                'application.runtime',
                'request.failed',
                $failed,
                $traceId
            );
            $this->profiler->event(
                'application.runtime',
                'request.failed',
                $failed
            );
            throw $error;
        } finally {
            $this->profiler->stop([
                'component' => self::class,
                'status' => $status,
                'duration_ms' => round(
                    (microtime(true) - $startedAt) * 1000,
                    3
                ),
            ]);
        }
    }

    public function run(): void
    {
        $this->handle()->send();
    }

    private function requestMethod(): string
    {
        $method = strtoupper(trim((string) (
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        )));

        return preg_match('/^[A-Z]{3,16}$/', $method) === 1
            ? $method
            : 'UNKNOWN';
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());

        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OPUS_APPLICATION_RUNTIME_FAILED';
    }
}