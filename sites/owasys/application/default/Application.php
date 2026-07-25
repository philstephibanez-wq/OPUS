<?php
declare(strict_types=1);

use Opus\Log\Logger;
use Opus\Profiler\Profiler;

/** Singleton composition root for the autonomous OWASYS application. */
final class OwasysApplication
{
    public const CONTRACT = 'OWASYS_APPLICATION_SINGLETON_V2';

    private static ?self $instance = null;

    private readonly OwasysRuntimeController $frontend;
    private readonly OwasysCreationController $creation;
    private readonly OwasysBackendApiController $backend;
    private readonly Logger $logger;
    private readonly Profiler $profiler;
    private readonly string $runtimeMode;

    /** @param array<string,mixed> $siteConfig */
    private function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig
    ) {
        $opusRoot = dirname(dirname($this->siteRoot));
        $session = new OwasysAuthSession();
        $security = new OwasysRuntimeSecurity($siteRoot, $siteConfig);
        $renderer = new OwasysScorePageRenderer($siteRoot);
        $registry = new OwasysRegistryModel(
            $siteRoot,
            $opusRoot,
            OwasysApplicationSingletonInspector::instance($opusRoot)
        );

        $this->frontend = new OwasysRuntimeController(
            $siteRoot,
            $siteConfig,
            $session,
            $security,
            $renderer
        );
        $this->creation = new OwasysCreationController(
            $siteRoot,
            $siteConfig,
            $session,
            $security,
            $renderer,
            $registry,
            new OwasysApplicationCreationModel($siteRoot, $registry)
        );
        $this->backend = new OwasysBackendApiController(
            $siteRoot,
            $opusRoot
        );
        $this->logger = new Logger(
            $siteRoot . '/var/logs',
            'owasys-frontend.log'
        );
        $this->profiler = new Profiler($siteRoot . '/var/profiler');
        $this->runtimeMode = self::resolveRuntimeMode();
    }

    /** @param array<string,mixed> $siteConfig */
    public static function instance(
        string $siteRoot,
        array $siteConfig
    ): self {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');

        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException(
                    'OWASYS_APPLICATION_SINGLETON_ROOT_MISMATCH'
                );
            }
            return self::$instance;
        }

        return self::$instance = new self($siteRoot, $siteConfig);
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
                'owasys.runtime',
                'request.received',
                $context,
                $traceId
            );
            $this->profiler->event(
                'owasys.runtime',
                'request.received',
                $context
            );

            $this->dispatch();
            $status = 'completed';
            $completed = [
                'runtime_mode' => $this->runtimeMode,
                'duration_ms' => round(
                    (microtime(true) - $startedAt) * 1000,
                    3
                ),
            ];
            $this->logger->info(
                'owasys.runtime',
                'request.completed',
                $completed,
                $traceId
            );
            $this->profiler->event(
                'owasys.runtime',
                'request.completed',
                $completed
            );
        } catch (Throwable $error) {
            $failed = [
                'runtime_mode' => $this->runtimeMode,
                'duration_ms' => round(
                    (microtime(true) - $startedAt) * 1000,
                    3
                ),
                'error_code' => $this->safeErrorCode($error),
            ];
            $this->logger->error(
                'owasys.runtime',
                'request.failed',
                $failed,
                $traceId
            );
            $this->profiler->event(
                'owasys.runtime',
                'request.failed',
                $failed
            );
            if (!headers_sent()) {
                header('X-OPUS-Trace-Id: ' . $traceId);
            }
            throw $error;
        } finally {
            $this->profiler->stop([
                'component' => self::class,
                'status' => $status,
                'runtime_mode' => $this->runtimeMode,
                'duration_ms' => round(
                    (microtime(true) - $startedAt) * 1000,
                    3
                ),
            ]);
        }
    }

    private function dispatch(): void
    {
        $backendRequest = $this->backend->matchesCurrentRequest();

        if ($this->runtimeMode === 'back') {
            if (!$backendRequest) {
                throw new RuntimeException(
                    'OWASYS_BACK_RUNTIME_ROUTE_FORBIDDEN'
                );
            }
            $this->backend->run();
            return;
        }

        if ($backendRequest) {
            throw new RuntimeException(
                'OWASYS_FRONT_RUNTIME_API_FORBIDDEN'
            );
        }
        if ($this->creation->matchesCurrentRequest()) {
            $this->creation->run();
            return;
        }
        $this->frontend->run();
    }

    /** @return array{runtime_mode:string,method:string,path:string} */
    private function requestContext(): array
    {
        $path = parse_url(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        );
        $path = is_string($path) ? $path : '/';
        if (strlen($path) > 512) {
            $path = substr($path, 0, 512);
        }
        return [
            'runtime_mode' => $this->runtimeMode,
            'method' => strtoupper((string) (
                $_SERVER['REQUEST_METHOD'] ?? 'GET'
            )),
            'path' => $path,
        ];
    }

    private static function resolveRuntimeMode(): string
    {
        $mode = getenv('OPUS_APPLICATION_RUNTIME_MODE');
        $mode = is_string($mode) ? strtolower(trim($mode)) : '';
        if (!in_array($mode, ['front', 'back'], true)) {
            throw new RuntimeException(
                'OWASYS_RUNTIME_MODE_REQUIRED'
            );
        }
        return $mode;
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OWASYS_RUNTIME_FAILED';
    }
}
