<?php
declare(strict_types=1);

use Opus\Http\Response;
use Opus\Http\Request;
use Opus\I18n\ApplicationTranslationRuntime;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;
use Opus\Profiler\WebProfilerController;
use Opus\Profiler\WebProfilerView;
use Opus\Template\ScoreTemplateRenderer;

/** Autonomous Singleton composition root for the OWASYS SCORE frontend. */
final class OwasysFrontApplication implements OwasysFrontApplicationInterface
{
    public const CONTRACT = 'OWASYS_FRONT_APPLICATION_SINGLETON_V1';
    private static ?self $instance = null;
    private readonly Logger $logger;
    private readonly Profiler $profiler;
    private readonly array $siteConfig;
    private readonly OwasysSessionRuntime $sessionRuntime;

    private function __construct(private readonly string $siteRoot)
    {
        $this->logger = new Logger($siteRoot . '/var/logs', 'owasys-front.log');
        $this->profiler = new Profiler($siteRoot . '/var/profiler/runtime');
        $this->siteConfig = \Opus\File\StructuredFileLoader::instance()->read(
            $siteRoot . '/config/site.json'
        );
        $this->sessionRuntime = new OwasysSessionRuntime($this->siteConfig);
    }

    public static function instance(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException('OWASYS_FRONT_SINGLETON_CONTEXT_MISMATCH');
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
        throw new RuntimeException('OWASYS_FRONT_SINGLETON_UNSERIALIZE_FORBIDDEN');
    }

    public function run(): void
    {
        $trace = $this->profiler->start();
        $traceId = $trace->getTraceId();
        putenv('OPUS_TRACE_ID=' . $traceId);
        $status = 'failed';
        $path = $this->requestPath();
        try {
            if (preg_match('~(?:^|/)profiler=[^/]*(?:/|$)~', trim($path, '/')) === 1) {
                throw new RuntimeException('OWASYS_PROFILER_QUERY_SYNTAX_REQUIRED');
            }
            if ($path === '/api' || str_starts_with($path, '/api/')) {
                throw new RuntimeException('OWASYS_FRONT_API_FORBIDDEN');
            }
            $this->logger->info('owasys.front', 'request.received', [
                'method' => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
                'path' => $path,
            ], $traceId);
            $this->profiler->event('owasys.front', 'request.received', [
                'path' => $path,
                'profiler_requested' => (string) ($_GET['profiler'] ?? '') === '1',
            ]);
            if ($this->isProfilerTracePath($path)) {
                $this->serveProfilerTrace();
                $status = 'completed';
                return;
            }
            [$controller, $creation, $source] = $this->components();
            if ($creation->matchesCurrentRequest()) {
                $this->profiler->event('routing', 'controller.selected', [
                    'controller' => 'creation',
                ]);
                $creation->run();
            } elseif ($source->matchesCurrentRequest()) {
                $this->profiler->event('routing', 'controller.selected', [
                    'controller' => 'source',
                ]);
                $source->run();
            } else {
                $this->profiler->event('routing', 'controller.selected', [
                    'controller' => 'runtime',
                ]);
                $controller->run();
            }
            $this->profiler->event('score', 'response.rendered', ['path' => $path]);
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
            $this->profiler->event(
                'owasys.front',
                'request.failed',
                ['error_code' => $code],
                'error'
            );
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

    private function isProfilerTracePath(string $path): bool
    {
        return preg_match(
            '~^/_opus/profiler/trace/[a-f0-9]{16,64}$~D',
            $path
        ) === 1;
    }

    private function serveProfilerTrace(): void
    {
        $environment = strtolower(trim((string) getenv('OPUS_ENV')));
        if (!in_array($environment, ['dev', 'local', 'development'], true)) {
            throw new RuntimeException('OPUS_PROFILER_ENVIRONMENT_FORBIDDEN');
        }
        $this->sessionRuntime->start();
        $session = new OwasysAuthSession();
        $security = new OwasysRuntimeSecurity($this->siteRoot, $this->siteConfig);
        $security->assertAllowed($session->user(), 'profiler', 'view');

        if (!headers_sent()) {
            header("Content-Security-Policy: frame-ancestors 'self'");
            header('X-Frame-Options: SAMEORIGIN');
        }
        (new WebProfilerController(
            $this->profiler,
            new WebProfilerView(),
            true
        ))->handle(Request::fromGlobals($this->siteRoot))->send();
    }

    /** @return array{0:OwasysRuntimeController,1:OwasysCreationController,2:OwasysSourceController} */
    private function components(): array
    {
        $session = new OwasysAuthSession();
        $security = new OwasysRuntimeSecurity($this->siteRoot, $this->siteConfig);
        $renderer = new OwasysScorePageRenderer($this->siteRoot);
        $registry = new OwasysRegistryModel($this->siteRoot);
        return [
            new OwasysRuntimeController(
                $this->siteRoot,
                $this->siteConfig,
                $session,
                $security,
                $renderer,
                $this->sessionRuntime
            ),
            new OwasysCreationController(
                $this->siteRoot,
                $this->siteConfig,
                $session,
                $security,
                $renderer,
                $registry,
                $this->sessionRuntime,
                new OwasysApplicationCreationModel(
                    $this->siteRoot,
                    $registry,
                    null,
                    $this->profiler
                )
            ),
            new OwasysSourceController(
                $this->siteRoot,
                $this->siteConfig,
                $session,
                $security,
                $renderer,
                $this->sessionRuntime,
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
        $current = $error;
        do {
            $message = trim($current->getMessage());
            if (preg_match('/^[A-Z0-9_:-]{3,240}$/D', $message) === 1) {
                return $message;
            }
            if (preg_match(
                '/(?:^|[^A-Z0-9_])((?:OPUS|OWASYS|SCORE)_[A-Z0-9_:-]{2,240})/',
                $message,
                $match
            ) === 1) {
                return rtrim((string) $match[1], ':');
            }
            $current = $current->getPrevious();
        } while ($current instanceof Throwable);

        return 'OWASYS_FRONT_RUNTIME_FAILED';
    }
}
