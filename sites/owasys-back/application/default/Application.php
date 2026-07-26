<?php
declare(strict_types=1);

use Opus\Http\Response;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;

/** Autonomous Singleton composition root for the OWASYS business backend. */
final class OwasysBackApplication implements OwasysBackApplicationInterface
{
    public const CONTRACT = 'OWASYS_BACK_APPLICATION_SINGLETON_V1';
    private static ?self $instance = null;
    private readonly Logger $logger;
    private readonly Profiler $profiler;
    private readonly OwasysBackendApiController $controller;

    private function __construct(private readonly string $siteRoot)
    {
        $this->logger = new Logger($siteRoot . '/var/logs', 'owasys-back.log');
        $this->profiler = new Profiler($siteRoot . '/var/profiler/runtime');
        $this->controller = new OwasysBackendApiController(
            $siteRoot,
            dirname(dirname($siteRoot))
        );
    }

    public static function instance(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException(
                    'OWASYS_BACK_SINGLETON_CONTEXT_MISMATCH'
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
            'OWASYS_BACK_SINGLETON_UNSERIALIZE_FORBIDDEN'
        );
    }

    public function run(): void
    {
        $incoming = trim((string) ($_SERVER['HTTP_X_OPUS_TRACE_ID'] ?? ''));
        $trace = $this->profiler->start(
            preg_match('/^[a-f0-9]{16,64}$/', $incoming) === 1
                ? $incoming : null
        );
        $traceId = $trace->getTraceId();
        $status = 'failed';
        try {
            if (!$this->controller->matchesCurrentRequest()) {
                throw new RuntimeException('OWASYS_BACK_ROUTE_FORBIDDEN');
            }
            $this->logger->info('owasys.back', 'request.received', [
                'method' => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
                'path' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            ], $traceId);
            $this->controller->run();
            $status = 'completed';
        } catch (Throwable $error) {
            $code = $this->safeErrorCode($error);
            $this->logger->error('owasys.back', 'request.failed', [
                'error_code' => $code,
                'exception_class' => $error::class,
                'exception_file' => $error->getFile(),
                'exception_line' => $error->getLine(),
            ], $traceId);
            Response::json([
                'contract' => 'OWASYS_BACK_ERROR_V1',
                'status' => 'failed',
                'error_code' => $code,
                'trace_id' => $traceId,
            ], str_contains($code, 'ROUTE_FORBIDDEN') ? 404 : 500)->send();
        } finally {
            $this->profiler->stop([
                'component' => self::class,
                'status' => $status,
                'trace_id' => $traceId,
            ]);
        }
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message : 'OWASYS_BACK_RUNTIME_FAILED';
    }
}
