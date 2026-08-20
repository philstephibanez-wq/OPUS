<?php
declare(strict_types=1);

use Opus\Http\Response;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;

final class BackendApplication implements BackendApplicationInterface
{
    private static ?self $instance = null;
    private readonly Logger $logger;
    private readonly Profiler $profiler;
    private readonly BackendBackendApiController $controller;

    private function __construct(private readonly string $siteRoot)
    {
        $this->logger = new Logger($siteRoot . '/var/logs', 'backend.log');
        $this->profiler = new Profiler($siteRoot . '/var/profiler');
        $this->controller = new BackendBackendApiController(
            $siteRoot,
            dirname(dirname($siteRoot))
        );
    }

    public static function instance(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException('OPUS_APPLICATION_SINGLETON_ROOT_MISMATCH');
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
        throw new RuntimeException('OPUS_APPLICATION_SINGLETON_UNSERIALIZE_FORBIDDEN');
    }

    public function run(): void
    {
        $incoming = trim((string) ($_SERVER['HTTP_X_OPUS_TRACE_ID'] ?? ''));
        $trace = $this->profiler->start(
            preg_match('/^[a-f0-9]{16,64}$/', $incoming) === 1 ? $incoming : null
        );
        $traceId = $trace->getTraceId();
        $status = 'failed';
        try {
            if (!$this->controller->matchesCurrentRequest()) {
                throw new RuntimeException('OPUS_BACKEND_ROUTE_FORBIDDEN');
            }
            $this->logger->info('application.backend', 'request.received', [], $traceId);
            $this->controller->run();
            $status = 'completed';
        } catch (Throwable $error) {
            $this->logger->error('application.backend', 'request.failed', [
                'error_code' => preg_match('/^[A-Z0-9_:-]{3,240}$/', $error->getMessage()) === 1
                    ? $error->getMessage() : 'OPUS_BACKEND_RUNTIME_FAILED',
            ], $traceId);
            Response::json([
                'contract' => 'OPUS_BACKEND_ERROR_V1',
                'status' => 'failed',
                'trace_id' => $traceId,
            ], 500)->send();
        } finally {
            $this->profiler->stop([
                'component' => self::class,
                'status' => $status,
                'trace_id' => $traceId,
            ]);
        }
    }
}