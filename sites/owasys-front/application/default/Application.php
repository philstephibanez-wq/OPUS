<?php
declare(strict_types=1);

use Opus\Http\Response;
use Opus\Http\Request;
use Opus\Http\LocalizedRouteResolver;
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
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $httpSpanId = $this->profiler->beginSpan('http', 'http.request', [
            'method' => $method,
            'path' => $path,
        ]);
        $httpSpanEnded = false;
        try {
            $this->profiler->event('http', 'http.request.received', [
                'method' => $method,
                'path' => $path,
            ], 'success', $httpSpanId);
            if (preg_match(
                '~(?:^|/)profiler=[^/]*(?:/|$)~',
                trim($path, '/')
            ) === 1) {
                throw new RuntimeException(
                    'OWASYS_PROFILER_QUERY_SYNTAX_REQUIRED'
                );
            }
            if ($path === '/api' || str_starts_with($path, '/api/')) {
                throw new RuntimeException('OWASYS_FRONT_API_FORBIDDEN');
            }
            $this->logger->info('owasys.front', 'request.received', [
                'method' => $method,
                'path' => $path,
            ], $traceId);
            $this->profiler->event('owasys.front', 'request.received', [
                'path' => $path,
                'profiler_requested' =>
                    (string) ($_GET['profiler'] ?? '') === '1',
            ]);
            if ($this->isProfilerTracePath($path)) {
                $this->recordRoutingDecision(
                    $path,
                    'profiler',
                    WebProfilerController::class,
                    'handle',
                    'application/default/Application.php::isProfilerTracePath',
                    $httpSpanId
                );
                $this->serveProfilerTrace($httpSpanId);
                $status = 'completed';
                $responseStatus = http_response_code();
                $this->profiler->event('http', 'http.response.sent', [
                    'status_code' => $responseStatus,
                ], 'success', $httpSpanId);
                $this->profiler->endSpan($httpSpanId, 'success', [
                    'status_code' => $responseStatus,
                ]);
                $httpSpanEnded = true;
                return;
            }
            [$controller, $creation, $source, $security] =
                $this->components($httpSpanId);
            if ($creation->matchesCurrentRequest()) {
                $this->recordRoutingDecision(
                    $path,
                    'creation',
                    OwasysCreationController::class,
                    'run',
                    'application/creation/controllers/CreationController.php::matchesCurrentRequest',
                    $httpSpanId
                );
                $creation->run();
            } elseif ($source->matchesCurrentRequest()) {
                $this->recordRoutingDecision(
                    $path,
                    'source',
                    OwasysSourceController::class,
                    'run',
                    'application/source/controllers/SourceController.php::matchesCurrentRequest',
                    $httpSpanId
                );
                $source->run();
            } elseif ($security->matchesCurrentRequest()) {
                $this->recordRoutingDecision(
                    $path,
                    'security',
                    OwasysSecurityController::class,
                    'run',
                    'application/security/controllers/SecurityController.php::matchesCurrentRequest',
                    $httpSpanId
                );
                $security->run();
            } else {
                $this->recordRoutingDecision(
                    $path,
                    'runtime',
                    OwasysRuntimeController::class,
                    'run',
                    'application/default/Application.php::runtimeFallback',
                    $httpSpanId
                );
                $controller->run();
            }
            $responseStatus = http_response_code();
            if ($responseStatus < 300 || $responseStatus >= 400) {
                $this->profiler->event(
                    'score',
                    'response.rendered',
                    ['path' => $path],
                    'success',
                    $httpSpanId
                );
            }
            $status = 'completed';
            $this->logger->info('owasys.front', 'request.completed', [
                'path' => $path,
            ], $traceId);
            $this->profiler->event('http', 'http.response.sent', [
                'status_code' => $responseStatus,
            ], 'success', $httpSpanId);
            $this->profiler->endSpan($httpSpanId, 'success', [
                'status_code' => $responseStatus,
            ]);
            $httpSpanEnded = true;
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
                'error',
                $httpSpanId
            );
            $this->profiler->event('http', 'http.exception.caught', [
                'error_code' => $code,
                'exception_class' => $error::class,
            ], 'error', $httpSpanId);
            $this->profiler->endSpan($httpSpanId, 'error', [
                'error_code' => $code,
            ]);
            $httpSpanEnded = true;
            if (!headers_sent()) {
                header('X-Opus-Trace-Id: ' . $traceId);
            }
            $this->renderFailure(
                $code,
                $traceId,
                $this->failureStatus($code)
            );
        } finally {
            if (!$httpSpanEnded) {
                $this->profiler->endSpan($httpSpanId, 'error', [
                    'error_code' => 'OWASYS_HTTP_REQUEST_INCOMPLETE',
                ]);
            }
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

    private function serveProfilerTrace(string $httpSpanId): void
    {
        $environment = strtolower(trim((string) getenv('OPUS_ENV')));
        if (!in_array(
            $environment,
            ['dev', 'local', 'development'],
            true
        )) {
            throw new RuntimeException(
                'OPUS_PROFILER_ENVIRONMENT_FORBIDDEN'
            );
        }
        $this->sessionRuntime->start();
        $session = new OwasysAuthSession();
        $security = new OwasysRuntimeSecurity(
            $this->siteRoot,
            $this->siteConfig,
            $this->profiler,
            $httpSpanId
        );
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

    /**
     * @return array{
     *   0:OwasysRuntimeController,
     *   1:OwasysCreationController,
     *   2:OwasysSourceController,
     *   3:OwasysSecurityController
     * }
     */
    private function components(string $httpSpanId): array
    {
        $session = new OwasysAuthSession();
        $security = new OwasysRuntimeSecurity(
            $this->siteRoot,
            $this->siteConfig,
            $this->profiler,
            $httpSpanId
        );
        $routing = is_array($this->siteConfig['routing'] ?? null)
            ? $this->siteConfig['routing']
            : [];
        $localizedRouteFile = trim(str_replace(
            '\\',
            '/',
            (string) ($routing['localized_routes'] ?? '')
        ), '/');
        if ($localizedRouteFile === ''
            || str_contains($localizedRouteFile, '..')) {
            throw new RuntimeException(
                'OWASYS_LOCALIZED_ROUTE_CONFIG_PATH_INVALID'
            );
        }
        $localizedRoutes = LocalizedRouteResolver::fromFile(
            $this->siteRoot . '/' . $localizedRouteFile,
            array_values(array_filter(
                is_array($this->siteConfig['locales'] ?? null)
                    ? $this->siteConfig['locales']
                    : [],
                'is_string'
            )),
            $this->profiler,
            $httpSpanId
        );
        $renderer = new OwasysScorePageRenderer(
            $this->siteRoot,
            $this->profiler,
            $httpSpanId,
            $session,
            $security
        );
        $registry = new OwasysRegistryModel($this->siteRoot);
        return [
            new OwasysRuntimeController(
                $this->siteRoot,
                $this->siteConfig,
                $session,
                $security,
                $renderer,
                $localizedRoutes,
                $this->sessionRuntime,
                $this->profiler,
                $httpSpanId
            ),
            new OwasysCreationController(
                $this->siteRoot,
                $this->siteConfig,
                $session,
                $security,
                $renderer,
                $localizedRoutes,
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
                $localizedRoutes,
                $this->sessionRuntime,
                new OwasysSourceModel($this->siteRoot)
            ),
            new OwasysSecurityController(
                $this->siteRoot,
                $this->siteConfig,
                $session,
                $security,
                $renderer,
                $localizedRoutes,
                $this->sessionRuntime,
                $this->profiler,
                $httpSpanId
            ),
        ];
    }

    private function failureStatus(string $code): int
    {
        if ($code === 'OPUS_ACL_DENIED'
            || str_starts_with($code, 'OPUS_ACL_DENIED:')) {
            return 403;
        }

        return 500;
    }

    private function renderFailure(
        string $code,
        string $traceId,
        int $statusCode = 500
    ): void {
        $locale = $this->failureLocale();
        $i18n = new ApplicationTranslationRuntime(
            $this->siteRoot . '/application',
            'default',
            $locale
        );
        $renderer = new ScoreTemplateRenderer(
            $this->siteRoot . '/application',
            $i18n,
            $this->profiler
        );
        $aclDenied = $code === 'OPUS_ACL_DENIED'
            || str_starts_with($code, 'OPUS_ACL_DENIED:');
        $parts = $aclDenied ? explode(':', $code, 3) : [];
        $assetBase = $this->failureAssetBase();

        Response::html($renderer->render(
            'default/templates/runtime-error.score',
            [
                'error' => [
                    'code' => $code,
                    'trace_id' => $traceId,
                    'status_code' => $statusCode,
                    'acl_denied' => $aclDenied,
                    'generic' => !$aclDenied,
                    'resource' => (string) ($parts[1] ?? ''),
                    'action' => (string) ($parts[2] ?? ''),
                    'return_url' => $this->requestPath(),
                    'locale' => $locale,
                    'score_css' => $assetBase . '/asset/css/owasys.css',
                    'theme_css' => $assetBase
                        . '/asset/themes/owasys/css/theme.css?v=p117q',
                ],
            ]
        ), $statusCode)->send();
    }

    private function failureLocale(): string
    {
        $segments = array_values(array_filter(
            explode('/', trim($this->requestPath(), '/')),
            static fn (string $segment): bool => $segment !== ''
        ));
        $candidate = (string) ($segments[0] ?? '');
        $locales = is_array($this->siteConfig['locales'] ?? null)
            ? $this->siteConfig['locales']
            : [];

        if ($candidate !== '' && in_array($candidate, $locales, true)) {
            return $candidate;
        }

        return (string) ($this->siteConfig['default_locale'] ?? 'fr-FR');
    }

    private function failureAssetBase(): string
    {
        $script = str_replace(
            '\\',
            '/',
            (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')
        );
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

        return $base === '.' || $base === '/' ? '' : $base;
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

    private function recordRoutingDecision(
        string $path,
        string $controllerId,
        string $controllerClass,
        string $controllerAction,
        string $ruleSource,
        string $httpSpanId
    ): void {
        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn (string $segment): bool => $segment !== ''
        ));
        $supportedLocales = is_array($this->siteConfig['locales'] ?? null)
            ? $this->siteConfig['locales']
            : [];
        $locale = in_array(
            $segments[0] ?? '',
            $supportedLocales,
            true
        )
            ? (string) array_shift($segments)
            : '';
        $route = implode('/', $segments);
        if ($route === '') {
            $route = $controllerId === 'profiler'
                ? trim($path, '/')
                : 'login';
        }
        $parameters = [];
        if ($locale !== '') {
            $parameters['locale'] = $locale;
        }
        if ($controllerId === 'source'
            && str_starts_with($route, 'source/')) {
            $parameters['source_path'] = substr(
                $route,
                strlen('source/')
            );
        }

        $context = [
            'request_path' => $path,
            'normalized_route' => $route,
            'rule_source' => $ruleSource,
            'route_parameters' => $parameters,
        ];
        $this->profiler->event(
            'http',
            'route.resolved',
            $context,
            'success',
            $httpSpanId
        );
        $this->profiler->event(
            'http',
            'controller.selected',
            $context + [
                'controller_id' => $controllerId,
                'controller_class' => $controllerClass,
                'controller_action' => $controllerAction,
            ],
            'success',
            $httpSpanId
        );
    }

    private function safeErrorCode(Throwable $error): string
    {
        $current = $error;
        do {
            $message = trim($current->getMessage());
            if (preg_match(
                '/^OPUS_ACL_DENIED:[a-z0-9._-]+:[a-z0-9._-]+$/D',
                $message
            ) === 1) {
                return $message;
            }
            if (preg_match(
                '/^[A-Z0-9_:-]{3,240}$/D',
                $message
            ) === 1) {
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
