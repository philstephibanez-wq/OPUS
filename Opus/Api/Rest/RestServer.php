<?php
declare(strict_types=1);

namespace Opus\Api\Rest;

use Opus\Api\Composer\ComposerCommandExecutor;
use Opus\Api\Composer\ComposerCommandExecutorInterface;
use Opus\Api\Composer\ComposerCommandRegistry;
use Opus\Api\Composer\ComposerCommandRegistryInterface;
use Opus\Api\Fsm\RestRequestStateMachine;
use Opus\Api\Security\RestIdentityInterface;
use Opus\Api\Security\RestRequestAuthenticator;
use Opus\Api\Security\RestRequestAuthenticatorInterface;
use Opus\File\File;
use Opus\File\StructuredFileLoader;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Log\Logger;
use Opus\Log\LoggerInterface;
use Opus\Profiler\Profiler;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Access\Engine\HierarchicalAclEngine;

/** Generic secured OPUS REST API dispatching resources to allow-listed Composer business commands. */
final class RestServer implements RestServerInterface
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly RestResourceCatalogInterface $resources,
        private readonly ComposerCommandRegistryInterface $registry,
        private readonly ComposerCommandExecutorInterface $executor,
        private readonly RestRequestAuthenticatorInterface $authenticator,
        private readonly LoggerInterface $logger,
        private readonly ProfilerInterface $profiler
    ) {
    }

    public static function fromRoot(string $opusRoot, string $configRelative): self
    {
        $root = rtrim(str_replace('\\', '/', $opusRoot), '/');
        if ($root === '' || !is_dir($root)) {
            throw new \RuntimeException('OPUS_REST_API_ROOT_INVALID');
        }
        $loader = StructuredFileLoader::instance();
        $config = $loader->read($root . '/' . self::safeRelative($configRelative));
        if (($config['contract'] ?? null) !== 'OPUS_REST_API_SERVER_CONFIG_V1') {
            throw new \RuntimeException('OPUS_REST_API_SERVER_CONFIG_CONTRACT_INVALID');
        }
        $catalogRelative = trim((string) ($config['resource_catalog'] ?? ''));
        $inlineResources = $config['resources'] ?? null;
        $externalCatalog = $catalogRelative !== ''
            ? RestResourceCatalog::fromFile($root . '/' . self::safeRelative($catalogRelative))
            : null;
        $inlineCatalog = is_array($inlineResources)
            ? RestResourceCatalog::fromArray($inlineResources, (string) ($config['base_path'] ?? ''))
            : null;
        if ($externalCatalog !== null && $inlineCatalog !== null) {
            $externalCatalog->assertPeerFingerprint($inlineCatalog->fingerprint());
        }
        $resources = $externalCatalog ?? $inlineCatalog;
        if ($resources === null) {
            throw new \RuntimeException('OPUS_REST_API_RESOURCE_CATALOG_MISSING');
        }
        $configuredBase = '/' . trim((string) ($config['base_path'] ?? ''), '/');
        if (!hash_equals($resources->basePath(), $configuredBase)) {
            throw new \RuntimeException('OPUS_REST_API_RESOURCE_CATALOG_BASE_MISMATCH');
        }
        $diagnostics = is_array($config['diagnostics'] ?? null) ? $config['diagnostics'] : [];
        $logFile = trim((string) ($diagnostics['log_file'] ?? ''));
        if (preg_match('/^[A-Za-z0-9._-]+\.log$/', $logFile) !== 1) {
            throw new \RuntimeException('OPUS_REST_API_LOG_FILE_INVALID');
        }
        $logger = new Logger(
            $root . '/' . self::safeRelative((string) ($diagnostics['log_directory'] ?? '')),
            $logFile
        );
        $profiler = new Profiler(
            $root . '/' . self::safeRelative((string) ($diagnostics['profiler_directory'] ?? ''))
        );
        return new self(
            $config,
            $resources,
            ComposerCommandRegistry::fromRoot(
                $root,
                self::safeRelative((string) ($config['operation_catalog'] ?? ''))
            ),
            new ComposerCommandExecutor(
                $root,
                self::composerCommand($root, $config),
                (int) ($config['timeout_seconds'] ?? 120),
                (int) ($config['max_output_bytes'] ?? 2097152),
                $logger,
                $profiler
            ),
            new RestRequestAuthenticator(
                is_array($config['authentication'] ?? null) ? $config['authentication'] : []
            ),
            $logger,
            $profiler
        );
    }

    public function handle(Request $request): Response
    {
        $path = '/' . trim($request->path, '/');
        $base = $this->resources->basePath();
        $locale = $this->locale();
        if ($request->method === 'GET' && $path === $base . '/status') {
            return Response::json([
                'contract' => 'OPUS_REST_API_RESPONSE_V1',
                'data' => [
                    'status' => 'ok',
                    'transport' => 'rest',
                    'business_boundary' => 'composer',
                    'locale' => $locale,
                    'catalog_fingerprint' => $this->resources->fingerprint(),
                ],
            ], 200, [
                'X-Opus-Rest-Catalog' => $this->resources->fingerprint(),
            ]);
        }
        if ($path === $base . '/status') {
            return $this->error(
                'OPUS_REST_API_METHOD_NOT_ALLOWED',
                405,
                $locale,
                ['Allow' => 'GET']
            );
        }

        [$route, $parameters, $allowed] = $this->resolve($request->method, $path);
        if ($route === null) {
            if ($allowed !== []) {
                return $this->error(
                    'OPUS_REST_API_METHOD_NOT_ALLOWED',
                    405,
                    $locale,
                    ['Allow' => implode(', ', $allowed)]
                );
            }
            return $this->error('OPUS_REST_API_RESOURCE_NOT_FOUND', 404, $locale);
        }
        return $this->dispatch($request, $route, $parameters, $locale);
    }

    /**
     * @param array<string,mixed> $route
     * @param array<string,string> $pathParameters
     */
    private function dispatch(
        Request $request,
        array $route,
        array $pathParameters,
        string $locale
    ): Response {
        $traceId = $this->traceId();
        $nonce = trim((string) ($_SERVER['HTTP_X_OPUS_REST_NONCE'] ?? ''));
        $fsmConfig = is_array($this->config['fsm'] ?? null) ? $this->config['fsm'] : [];
        $fsm = new RestRequestStateMachine(
            (string) ($fsmConfig['initial_state'] ?? 'received'),
            is_array($fsmConfig['transitions'] ?? null) ? $fsmConfig['transitions'] : []
        );
        $this->profiler->start($traceId);
        $profilerFinalized = false;
        $terminalStatus = 'failed';
        $operation = (string) ($route['operation'] ?? '');
        $this->logger->info('rest.api', 'request.received', [
            'method' => $request->method,
            'path' => $request->path,
        ], $traceId);

        try {
            if ($request->method === 'GET' && $request->body() !== '') {
                throw new \RuntimeException('OPUS_REST_API_GET_BODY_FORBIDDEN');
            }
            $this->resources->assertPeerFingerprint(
                (string) ($_SERVER['HTTP_X_OPUS_REST_CATALOG'] ?? '')
            );
            if (preg_match('/^[a-f0-9]{32,64}$/', $nonce) !== 1) {
                throw new \RuntimeException('OPUS_REST_API_NONCE_INVALID');
            }
            $identity = $this->authenticator->authenticate($request, $_SERVER);
            $fsm->transition('authenticated');
            $entry = $this->registry->operation($operation);
            $this->assertAuthorized($identity, $entry, $operation);
            $fsm->transition('authorized');

            $body = $request->body() === '' ? [] : $request->jsonBody();
            $data = $body['data'] ?? [];
            if (!is_array($data)) {
                throw new \RuntimeException('OPUS_REST_API_REQUEST_DATA_INVALID');
            }
            $parameters = array_merge(
                $data,
                $pathParameters,
                is_array($route['parameters'] ?? null) ? $route['parameters'] : []
            );
            $entry['argv'] = $this->registry->arguments($entry, $parameters);
            $commandRequest = [
                'contract' => 'OPUS_REST_API_COMPOSER_COMMAND_REQUEST_V1',
                'application_id' => self::applicationId($this->config),
                'trace_id' => $traceId,
                'request_id' => $nonce,
                'operation' => $operation,
                'actor' => $identity->toArray(),
                'parameters' => $parameters,
                'requested_at_utc' => gmdate('c'),
            ];
            $fsm->transition('dispatching');
            $commandResult = $this->executor->execute($entry, $commandRequest);
            $fsm->transition('succeeded');
            $terminalStatus = 'succeeded';
            $result = $commandResult['data'] ?? null;
            if (!is_array($result)) {
                throw new \RuntimeException('OPUS_REST_API_COMPOSER_RESULT_INVALID');
            }
            $headers = [
                'X-Opus-Trace-Id' => $traceId,
                'X-Opus-Rest-Catalog' => $this->resources->fingerprint(),
            ];
            $status = $this->resources->successStatus($route);
            $location = $this->resources->location($route, $parameters);
            if ($location !== '') {
                $headers['Location'] = $location;
            }
            if ($request->method === 'GET') {
                $etag = '"' . hash('sha256', json_encode($result, JSON_THROW_ON_ERROR)) . '"';
                $headers['ETag'] = $etag;
                if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
                    return Response::empty(304, $headers);
                }
            }
            $this->logger->info('rest.api', 'request.succeeded', [
                'request_id' => $nonce,
                'operation' => $operation,
                'status_code' => $status,
            ], $traceId);
            $payload = [
                'contract' => 'OPUS_REST_API_RESPONSE_V1',
                'data' => $result,
                'trace_id' => $traceId,
            ];
            if ($this->profilerRequested()) {
                $profilerFinalized = true;
                if ($this->finalizeProfiler(
                    $traceId,
                    $operation,
                    $terminalStatus,
                    $fsm->state()
                )) {
                    try {
                        $payload['profiler_records'] = $this->profiler->readTrace($traceId);
                    } catch (\Throwable $profilerError) {
                        $this->logProfilerFailure(
                            'profiler.read.failed',
                            $profilerError,
                            $traceId,
                            $operation
                        );
                    }
                }
            }
            return Response::json($payload, $status, $headers);
        } catch (\Throwable $error) {
            try {
                if ($fsm->state() !== 'failed') {
                    $fsm->transition('failed');
                }
            } catch (\Throwable) {
            }
            $code = $this->safeErrorCode($error);
            $status = match (true) {
                str_contains($code, 'AUTHENTICATION') => 401,
                str_contains($code, 'ACL') => 403,
                str_contains($code, 'UNKNOWN') => 404,
                str_contains($code, 'REPLAY') => 409,
                str_contains($code, 'CONFLICT') => 409,
                str_contains($code, 'CATALOG') => 409,
                default => 400,
            };
            $this->logger->error('rest.api', 'request.failed', [
                'request_id' => $nonce,
                'operation' => $operation,
                'error_code' => $code,
                'exception_class' => $error::class,
                'exception_file' => $error->getFile(),
                'exception_line' => $error->getLine(),
            ], $traceId);
            return $this->error($code, $status, $locale, ['X-Opus-Trace-Id' => $traceId]);
        } finally {
            if (!$profilerFinalized) {
                $this->finalizeProfiler(
                    $traceId,
                    $operation,
                    $terminalStatus,
                    $fsm->state()
                );
            }
        }
    }

    private function finalizeProfiler(
        string $traceId,
        string $operation,
        string $terminalStatus,
        string $fsmState
    ): bool {
        try {
            $this->profiler->stop([
                'component' => self::class,
                'trace_id' => $traceId,
                'operation' => $operation,
                'status' => $terminalStatus,
                'fsm_state' => $fsmState,
            ]);
            return true;
        } catch (\Throwable $profilerError) {
            $this->logProfilerFailure(
                'profiler.stop.failed',
                $profilerError,
                $traceId,
                $operation
            );
            return false;
        }
    }

    private function logProfilerFailure(
        string $event,
        \Throwable $error,
        string $traceId,
        string $operation
    ): void {
        try {
            $this->logger->error('rest.api', $event, [
                'operation' => $operation,
                'exception_class' => $error::class,
                'exception_file' => $error->getFile(),
                'exception_line' => $error->getLine(),
            ], $traceId);
        } catch (\Throwable) {
        }
    }

    private function profilerRequested(): bool
    {
        $environment = strtolower(trim((string) getenv('OPUS_ENV')));
        return in_array($environment, ['dev', 'local', 'development'], true)
            && trim((string) ($_SERVER['HTTP_X_OPUS_PROFILER'] ?? '')) === '1';
    }

    /**
     * @return array{0:?array<string,mixed>,1:array<string,string>,2:list<string>}
     */
    private function resolve(string $method, string $path): array
    {
        return $this->resources->resolve($method, $path);
    }

    /** @param array<string,mixed> $entry */
    private function assertAuthorized(
        RestIdentityInterface $identity,
        array $entry,
        string $operation
    ): void {
        $required = is_array($entry['roles'] ?? null)
            ? array_values(array_filter($entry['roles'], 'is_string')) : [];
        if ($required === []) {
            throw new \RuntimeException('OPUS_REST_API_ACL_DENIED');
        }

        $engine = HierarchicalAclEngine::fromConfig([
            'roles' => [],
            'resources' => [
                'rest.operation' => [],
            ],
            'rules' => [[
                'effect' => 'allow',
                'roles' => $required,
                'resources' => ['rest.operation'],
                'privileges' => ['execute'],
                'description' => 'REST operation allow-list from Composer operation catalog',
            ]],
        ]);
        $decision = $engine->decide(
            $operation !== '' ? $operation : 'rest.operation',
            [
                'resource' => 'rest.operation',
                'privilege' => 'execute',
            ],
            $identity
        );
        if (!$decision->isGranted()) {
            throw new \RuntimeException(
                'OPUS_REST_API_ACL_DENIED: ' . $decision->reason()
            );
        }
    }

    /** @param array<string,mixed> $config */
    private static function applicationId(array $config): string
    {
        $id = trim((string) ($config['application_id'] ?? ''));
        if (preg_match('/^[a-z][a-z0-9-]*$/', $id) !== 1) {
            throw new \RuntimeException('OPUS_REST_API_APPLICATION_ID_INVALID');
        }
        return $id;
    }

    private function locale(): string
    {
        $supported = is_array($this->config['supported_locales'] ?? null)
            ? array_values(array_filter($this->config['supported_locales'], 'is_string')) : [];
        $default = trim((string) ($this->config['default_locale'] ?? ''));
        if ($supported === [] || $default === '') {
            throw new \RuntimeException('OPUS_REST_API_LOCALE_CONFIG_INVALID');
        }
        return BrowserLocaleNegotiator::forLocales($supported, $default)
            ->negotiate(is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null)
                ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null)->value;
    }

    /** @param array<string,string> $headers */
    private function error(
        string $code,
        int $status,
        string $locale,
        array $headers = []
    ): Response {
        $headers = array_replace([
            'X-Opus-Rest-Catalog' => $this->resources->fingerprint(),
        ], $headers);
        return Response::json([
            'contract' => 'OPUS_REST_API_ERROR_V1',
            'error_code' => $code,
            'locale' => $locale,
        ], $status, $headers);
    }

    private function safeErrorCode(\Throwable $error): string
    {
        $message = trim($error->getMessage());
        if (preg_match('/^([A-Z][A-Z0-9_]{2,119})(?::|$)/D', $message, $matches) === 1) {
            return $matches[1];
        }
        return 'OPUS_REST_API_REQUEST_FAILED';
    }

    private function traceId(): string
    {
        $incoming = trim((string) ($_SERVER['HTTP_X_OPUS_TRACE_ID'] ?? ''));
        return preg_match('/^[a-f0-9]{16,64}$/', $incoming) === 1
            ? $incoming : bin2hex(random_bytes(16));
    }

    /** @param array<string,mixed> $config @return list<string> */
    private static function composerCommand(string $root, array $config): array
    {
        $selected = is_array($config['composer_command'] ?? null)
            ? array_values(array_filter($config['composer_command'], 'is_string')) : [];
        if ($selected === ['@composer']) {
            return self::discoverComposerCommand($root);
        }
        if ($selected === ['@in-process']) {
            return $selected;
        }
        if ($selected === []) {
            throw new \RuntimeException('OPUS_REST_API_COMPOSER_COMMAND_MISSING');
        }
        $resolved = [];
        foreach ($selected as $index => $part) {
            $part = trim($part);
            if ($part === '@php') {
                $resolved[] = PHP_BINARY;
                continue;
            }
            if ($part === '') {
                throw new \RuntimeException('OPUS_REST_API_COMPOSER_COMMAND_INVALID');
            }
            if ($index > 0 && str_ends_with(strtolower($part), '.phar')) {
                $absolute = $root . '/' . self::safeRelative($part);
                if (!File::instance()->exists($absolute)) {
                    throw new \RuntimeException('OPUS_REST_API_COMPOSER_PHAR_MISSING');
                }
                $resolved[] = $absolute;
                continue;
            }
            if (preg_match('/^[A-Za-z0-9._-]+$/', $part) !== 1) {
                throw new \RuntimeException('OPUS_REST_API_COMPOSER_COMMAND_INVALID');
            }
            $resolved[] = $part;
        }
        return $resolved;
    }

    /** @return list<string> */
    private static function discoverComposerCommand(string $root): array
    {
        $candidates = [$root . '/composer.phar'];
        $explicit = getenv('OPUS_COMPOSER_PHAR');
        if (is_string($explicit) && trim($explicit) !== '') {
            array_unshift($candidates, trim($explicit, " \t\n\r\0\x0B\""));
        }
        $path = getenv('PATH');
        if (is_string($path) && $path !== '') {
            foreach (explode(PATH_SEPARATOR, $path) as $directory) {
                $directory = trim($directory, " \t\n\r\0\x0B\"");
                if ($directory !== '') {
                    $candidates[] = rtrim($directory, '/\\') . '/composer.phar';
                }
            }
        }
        foreach ([getenv('ProgramData'), getenv('APPDATA'), getenv('LOCALAPPDATA')] as $knownRoot) {
            if (is_string($knownRoot) && trim($knownRoot) !== '') {
                $candidates[] = rtrim(trim($knownRoot), '/\\')
                    . '/ComposerSetup/bin/composer.phar';
            }
        }
        foreach (array_unique($candidates) as $candidate) {
            $candidate = str_replace('\\', '/', trim($candidate));
            if (self::absolutePath($candidate) && File::instance()->exists($candidate)) {
                return [PHP_BINARY, $candidate];
            }
        }
        throw new \RuntimeException('OPUS_REST_API_COMPOSER_NOT_FOUND');
    }

    private static function absolutePath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1;
    }

    private static function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')
            || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new \RuntimeException('OPUS_REST_API_CONFIG_PATH_INVALID');
        }
        return $path;
    }
}
