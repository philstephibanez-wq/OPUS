<?php
declare(strict_types=1);

use Opus\Http\Response;
use Opus\I18n\ApplicationTranslationRuntime;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;
use Opus\Template\ScoreTemplateRenderer;

/** Autonomous Singleton composition root for the OWASYS SCORE frontend. */
final class OwasysFrontApplication implements OwasysFrontApplicationInterface
{
    public const CONTRACT = 'OWASYS_FRONT_APPLICATION_SINGLETON_V1';
    private static ?self $instance = null;
    private readonly Logger $logger;
    private readonly Profiler $profiler;

    private function __construct(private readonly string $siteRoot)
    {
        $this->logger = new Logger($siteRoot . '/var/logs', 'owasys-front.log');
        $this->profiler = new Profiler($siteRoot . '/var/profiler/runtime');
    }

    public static function instance(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException(
                    'OWASYS_FRONT_SINGLETON_CONTEXT_MISMATCH'
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
            'OWASYS_FRONT_SINGLETON_UNSERIALIZE_FORBIDDEN'
        );
    }

    public function run(): void
    {
        $trace = $this->profiler->start();
        $traceId = $trace->getTraceId();
        putenv('OPUS_TRACE_ID=' . $traceId);
        $status = 'failed';
        $path = $this->requestPath();
        try {
            if ($path === '/api' || str_starts_with($path, '/api/')) {
                throw new RuntimeException('OWASYS_FRONT_API_FORBIDDEN');
            }
            $this->logger->info('owasys.front', 'request.received', [
                'method' => strtoupper((string) (
                    $_SERVER['REQUEST_METHOD'] ?? 'GET'
                )),
                'path' => $path,
            ], $traceId);
            $this->profiler->event('owasys.front', 'request.received', [
                'path' => $path,
            ]);
            [$controller, $creation, $source] = $this->components();
            if ($creation->matchesCurrentRequest()) {
                $creation->run();
            } elseif ($source->matchesCurrentRequest()) {
                $source->run();
            } else {
                $controller->run();
            }
            $status = 'completed';
            $this->logger->info('owasys.front', 'request.completed', [
                'path' => $path,
            ], $traceId);
        } catch (Throwable $error) {
            $code = $this->safeErrorCode($error);
            $this->logger->error('owasys.front', 'request.failed', [
                'error_code' => $code,
                'exception_class' => $error::class,
                'exception_file' => $error->getFile(),
                'exception_line' => $error->getLine(),
            ], $traceId);
            $this->profiler->event('owasys.front', 'request.failed', [
                'error_code' => $code,
            ]);
            if (!headers_sent()) {
                header('X-Opus-Trace-Id: ' . $traceId);
            }
            $this->renderFailure($code, $traceId);
        } finally {
            $this->profiler->stop([
                'component' => self::class,
                'status' => $status,
                'trace_id' => $traceId,
            ]);
            putenv('OPUS_TRACE_ID');
        }
    }

    /** @return array{0:OwasysRuntimeController,1:OwasysCreationController,2:OwasysSourceController} */
    private function components(): array
    {
        $siteConfig = \Opus\File\StructuredFileLoader::instance()->read(
            $this->siteRoot . '/config/site.json'
        );
        $session = new OwasysAuthSession();
        $security = new OwasysRuntimeSecurity($this->siteRoot, $siteConfig);
        $renderer = new OwasysScorePageRenderer($this->siteRoot);
        $registry = new OwasysRegistryModel($this->siteRoot);
        return [
            new OwasysRuntimeController(
                $this->siteRoot,
                $siteConfig,
                $session,
                $security,
                $renderer
            ),
            new OwasysCreationController(
                $this->siteRoot,
                $siteConfig,
                $session,
                $security,
                $renderer,
                $registry,
                new OwasysApplicationCreationModel($this->siteRoot, $registry)
            ),
            new OwasysSourceController(
                $this->siteRoot,
                $siteConfig,
                $session,
                $security,
                $renderer,
                new OwasysSourceModel($this->siteRoot)
            ),
        ];
    }

    private function renderFailure(string $code, string $traceId): void
    {
        $i18n = new ApplicationTranslationRuntime(
            $this->siteRoot . '/application',
            'default',
            'fr-FR'
        );
        $renderer = new ScoreTemplateRenderer(
            $this->siteRoot . '/application',
            $i18n
        );
        Response::html($renderer->render(
            'default/templates/runtime-error.score',
            ['error' => ['code' => $code, 'trace_id' => $traceId]]
        ), 500)->send();
    }

    private function requestPath(): string
    {
        $path = parse_url(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        );
        return '/' . trim(
            is_string($path) ? rawurldecode($path) : '/',
            '/'
        );
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OWASYS_FRONT_RUNTIME_FAILED';
    }
}
