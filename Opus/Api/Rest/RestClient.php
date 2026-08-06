<?php
declare(strict_types=1);

namespace Opus\Api\Rest;

use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Profiler\ProfilerInterface;

/** Secured server-side client for the OPUS resource-oriented REST API. */
final class RestClient implements RestClientInterface
{
    /** @var list<string> */
    private const PROFILER_SENSITIVE_KEYS = [
        'authorization',
        'author',
        'commit_message',
        'confirmation',
        'content',
        'diff',
        'email',
        'hmac',
        'message',
        'new_content',
        'password',
        'password_hash',
        'profiler_records',
        'restore_confirmation',
        'secret',
        'server_content',
        'staged',
        'subject',
        'unstaged',
        'token',
    ];

    private function __construct(
        private readonly string $baseUrl,
        private readonly string $tokenEnvironment,
        private readonly string $hmacEnvironment,
        private readonly int $timeoutSeconds,
        private readonly int $maxRequestBytes,
        private readonly int $maxResponseBytes,
        private readonly RestResourceCatalogInterface $resources,
        private readonly ?ProfilerInterface $profiler
    ) {
    }

    public static function fromConfig(
        string $configFile,
        ?ProfilerInterface $profiler = null
    ): self {
        $configFile = str_replace('\\', '/', trim($configFile));
        $config = StructuredFileLoader::instance()->read($configFile);
        if (($config['contract'] ?? null)
            !== 'OPUS_REST_API_CLIENT_CONFIG_V1') {
            throw new \RuntimeException(
                'OPUS_REST_API_CLIENT_CONFIG_CONTRACT_INVALID'
            );
        }
        $environment = self::environmentName(
            (string) ($config['base_url_env'] ?? ''),
            'OPUS_REST_API_BASE_URL_ENV_INVALID'
        );
        $baseUrl = self::environmentValue(
            $environment,
            'OPUS_REST_API_BASE_URL_NOT_CONFIGURED'
        );
        self::assertBaseUrl($baseUrl);

        $timeout = (int) ($config['timeout_seconds'] ?? 0);
        $maxRequest = (int) ($config['max_request_bytes'] ?? 0);
        $maxResponse = (int) ($config['max_response_bytes'] ?? 0);
        if ($timeout < 1 || $timeout > 600) {
            throw new \RuntimeException('OPUS_REST_API_TIMEOUT_INVALID');
        }
        if ($maxRequest < 4096 || $maxRequest > 16777216) {
            throw new \RuntimeException(
                'OPUS_REST_API_REQUEST_LIMIT_INVALID'
            );
        }
        if ($maxResponse < 4096 || $maxResponse > 16777216) {
            throw new \RuntimeException(
                'OPUS_REST_API_RESPONSE_LIMIT_INVALID'
            );
        }

        $catalogRelative = self::safeRelative(
            (string) ($config['resource_catalog'] ?? '')
        );
        $catalogFile = rtrim(
            str_replace('\\', '/', dirname($configFile)),
            '/'
        ) . '/' . $catalogRelative;

        return new self(
            rtrim($baseUrl, '/'),
            self::environmentName(
                (string) ($config['token_env'] ?? ''),
                'OPUS_REST_API_TOKEN_ENV_INVALID'
            ),
            self::environmentName(
                (string) ($config['hmac_env'] ?? ''),
                'OPUS_REST_API_HMAC_ENV_INVALID'
            ),
            $timeout,
            $maxRequest,
            $maxResponse,
            RestResourceCatalog::fromFile($catalogFile),
            $profiler
        );
    }

    public function request(
        string $method,
        string $resource,
        array $body,
        array $actor
    ): array {
        $method = strtoupper(trim($method));
        $resource = '/' . ltrim(trim($resource), '/');
        $route = $this->resources->assertRequest($method, $resource);
        if ($method === 'GET' && $body !== []) {
            throw new \RuntimeException(
                'OPUS_REST_API_GET_BODY_FORBIDDEN'
            );
        }

        $actor = self::actor($actor);
        $json = $body === []
            ? ''
            : Json::instance()->encode(['data' => $body]);
        if (strlen($json) > $this->maxRequestBytes) {
            throw new \RuntimeException(
                'OPUS_REST_API_REQUEST_TOO_LARGE'
            );
        }

        $spanId = $this->beginRestSpan(
            $method,
            $resource,
            strlen($json),
            $body
        );
        $status = 0;
        $responseBytes = 0;
        $responseHeaders = [];
        $responseBody = null;
        $traceId = null;

        try {
            $actorJson = Json::instance()->encode($actor, false);
            if (strlen($actorJson) > 16384) {
                throw new \RuntimeException(
                    'OPUS_REST_API_ACTOR_TOO_LARGE'
                );
            }
            $actorHeader = rtrim(strtr(
                base64_encode($actorJson),
                '+/',
                '-_'
            ), '=');
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));
            $signature = hash_hmac(
                'sha256',
                self::canonical(
                    $method,
                    $resource,
                    $timestamp,
                    $nonce,
                    $actorHeader,
                    $json
                ),
                $this->secret($this->hmacEnvironment, 'HMAC')
            );
            $headers = [
                'Accept: application/json',
                'Authorization: Bearer '
                    . $this->secret($this->tokenEnvironment, 'TOKEN'),
                'Content-Type: application/json',
                'X-Opus-Rest-Timestamp: ' . $timestamp,
                'X-Opus-Rest-Nonce: ' . $nonce,
                'X-Opus-Rest-Signature: ' . $signature,
                'X-Opus-Rest-Actor: ' . $actorHeader,
                'X-Opus-Rest-Catalog: '
                    . $this->resources->fingerprint(),
            ];
            $traceId = $this->traceId();
            if ($traceId !== null) {
                $headers[] = 'X-Opus-Trace-Id: ' . $traceId;
                if (self::profilerEnvironment()) {
                    $headers[] = 'X-Opus-Profiler: 1';
                }
            }

            $context = stream_context_create(['http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $json,
                'ignore_errors' => true,
                'timeout' => $this->timeoutSeconds,
                'follow_location' => 0,
                'max_redirects' => 0,
                'protocol_version' => 1.1,
            ]]);
            $stream = @fopen(
                $this->baseUrl . $resource,
                'rb',
                false,
                $context
            );
            if (!is_resource($stream)) {
                throw new \RuntimeException(
                    'OPUS_REST_API_TRANSPORT_FAILED'
                );
            }
            try {
                $response = stream_get_contents(
                    $stream,
                    $this->maxResponseBytes + 1
                );
                if (!is_string($response)) {
                    throw new \RuntimeException(
                        'OPUS_REST_API_TRANSPORT_FAILED'
                    );
                }
                $wrapper = stream_get_meta_data($stream)['wrapper_data']
                    ?? ($http_response_header ?? []);
            } finally {
                fclose($stream);
            }

            $responseBytes = strlen($response);
            if ($responseBytes > $this->maxResponseBytes) {
                throw new \RuntimeException(
                    'OPUS_REST_API_RESPONSE_TOO_LARGE'
                );
            }
            $rawHeaders = is_array($wrapper)
                ? array_values(array_filter($wrapper, 'is_string'))
                : [];
            $status = self::status($rawHeaders);
            $responseHeaders = self::safeResponseHeaders($rawHeaders);
            self::assertContentLength(
                $responseHeaders,
                $this->maxResponseBytes
            );
            $responseTraceId = $this->assertResponseHeaders(
                $responseHeaders,
                $traceId
            );

            $decoded = Json::instance()->parse($response, $resource);
            $responseBody = $decoded;
            if ($status < 200 || $status >= 300) {
                if (($decoded['contract'] ?? null)
                    !== 'OPUS_REST_API_ERROR_V1') {
                    throw new \RuntimeException(
                        'OPUS_REST_API_ERROR_CONTRACT_INVALID'
                    );
                }
                $code = trim((string) ($decoded['error_code'] ?? ''));
                throw new \RuntimeException(
                    preg_match('/^[A-Z0-9_:-]{3,240}$/D', $code) === 1
                        ? $code
                        : 'OPUS_REST_API_REQUEST_FAILED'
                );
            }

            $expectedStatus = $this->resources->successStatus($route);
            if ($status !== $expectedStatus) {
                throw new \RuntimeException(
                    'OPUS_REST_API_SUCCESS_STATUS_MISMATCH'
                );
            }
            if (($decoded['contract'] ?? null)
                !== 'OPUS_REST_API_RESPONSE_V1') {
                throw new \RuntimeException(
                    'OPUS_REST_API_RESPONSE_CONTRACT_INVALID'
                );
            }
            if (!hash_equals(
                $responseTraceId,
                self::responseTraceId($decoded)
            )) {
                throw new \RuntimeException(
                    'OPUS_REST_API_RESPONSE_TRACE_MISMATCH'
                );
            }
            $data = $decoded['data'] ?? null;
            if (!is_array($data)) {
                throw new \RuntimeException(
                    'OPUS_REST_API_RESPONSE_DATA_INVALID'
                );
            }

            $records = $decoded['profiler_records'] ?? [];
            self::assertProfilerRecords($records);
            if ($records !== [] && $spanId !== null) {
                $this->profiler?->importRecords($records, $spanId);
            }
            $this->finishRestSpan($spanId, 'success', [
                'event_type' => 'rest.response.received',
                'http_status' => $status,
                'response_bytes' => $responseBytes,
                'response_headers' => $responseHeaders,
                'response_body' => $data,
            ]);

            return $data;
        } catch (\Throwable $cause) {
            $this->finishRestSpan($spanId, 'error', [
                'event_type' => 'rest.request.failed',
                'http_status' => $status > 0 ? $status : null,
                'response_bytes' => $responseBytes,
                'response_headers' => $responseHeaders,
                'response_body' => $responseBody,
                'error_code' => self::errorCode($cause),
                'exception_class' => $cause::class,
            ]);
            throw $cause;
        }
    }

    /** @param list<string> $headers */
    private static function status(array $headers): int
    {
        $status = 0;
        foreach ($headers as $header) {
            if (preg_match(
                '/^HTTP\/\S+\s+(\d{3})/',
                $header,
                $match
            ) === 1) {
                $status = (int) $match[1];
            }
        }
        if ($status < 100 || $status > 599) {
            throw new \RuntimeException(
                'OPUS_REST_API_RESPONSE_STATUS_INVALID'
            );
        }
        return $status;
    }

    /** @param array<string,mixed> $requestBody */
    private function beginRestSpan(
        string $method,
        string $resource,
        int $requestBytes,
        array $requestBody
    ): ?string {
        if ($this->profiler === null
            || $this->profiler->getActiveTrace() === null) {
            return null;
        }
        return $this->profiler->beginSpan(
            'rest',
            'rest.request.started',
            [
                'event_type' => 'rest.request.started',
                'target_service' => (string) parse_url(
                    $this->baseUrl,
                    PHP_URL_HOST
                ),
                'method' => $method,
                'route' => $resource,
                'request_bytes' => $requestBytes,
                'catalog_fingerprint' => $this->resources->fingerprint(),
                'request_headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ],
                'request_body' => self::safeProfilerPayload(
                    $requestBody
                ),
            ]
        );
    }

    /** @param list<string> $headers @return array<string,string> */
    private static function safeResponseHeaders(array $headers): array
    {
        $allowed = [
            'content-length',
            'content-type',
            'etag',
            'location',
            'x-opus-rest-catalog',
            'x-opus-trace-id',
        ];
        $result = [];
        foreach ($headers as $header) {
            if (!str_contains($header, ':')) {
                continue;
            }
            [$name, $value] = array_map(
                'trim',
                explode(':', $header, 2)
            );
            $normalized = strtolower($name);
            if (in_array($normalized, $allowed, true)) {
                $result[$normalized] = $value;
            }
        }
        return $result;
    }

    /** @param array<string,string> $headers */
    private static function assertContentLength(
        array $headers,
        int $maximum
    ): void {
        if (!array_key_exists('content-length', $headers)) {
            return;
        }
        $length = trim($headers['content-length']);
        if (preg_match('/^[0-9]{1,12}$/D', $length) !== 1
            || (int) $length > $maximum) {
            throw new \RuntimeException(
                'OPUS_REST_API_RESPONSE_TOO_LARGE'
            );
        }
    }

    /**
     * @param array<string,string> $headers
     */
    private function assertResponseHeaders(
        array $headers,
        ?string $requestTraceId
    ): string {
        $contentType = strtolower(trim(
            (string) ($headers['content-type'] ?? '')
        ));
        if (preg_match(
            '~^application/json(?:\s*;|$)~D',
            $contentType
        ) !== 1) {
            throw new \RuntimeException(
                'OPUS_REST_API_RESPONSE_CONTENT_TYPE_INVALID'
            );
        }
        $this->resources->assertPeerFingerprint(
            (string) ($headers['x-opus-rest-catalog'] ?? '')
        );

        $traceId = strtolower(trim(
            (string) ($headers['x-opus-trace-id'] ?? '')
        ));
        if (preg_match('/^[a-f0-9]{16,64}$/D', $traceId) !== 1) {
            throw new \RuntimeException(
                'OPUS_REST_API_RESPONSE_TRACE_INVALID'
            );
        }
        if ($requestTraceId !== null
            && !hash_equals($requestTraceId, $traceId)) {
            throw new \RuntimeException(
                'OPUS_REST_API_RESPONSE_TRACE_MISMATCH'
            );
        }
        return $traceId;
    }

    /** @param array<string,mixed> $decoded */
    private static function responseTraceId(array $decoded): string
    {
        $traceId = strtolower(trim((string) ($decoded['trace_id'] ?? '')));
        if (preg_match('/^[a-f0-9]{16,64}$/D', $traceId) !== 1) {
            throw new \RuntimeException(
                'OPUS_REST_API_RESPONSE_TRACE_INVALID'
            );
        }
        return $traceId;
    }

    private static function assertProfilerRecords(mixed $records): void
    {
        if (!is_array($records)
            || !self::isList($records)
            || count($records) > 4096
            || array_filter($records, 'is_array') !== $records) {
            throw new \RuntimeException(
                'OPUS_REST_API_PROFILER_RECORDS_INVALID'
            );
        }
    }

    /** @param array<mixed> $value */
    private static function isList(array $value): bool
    {
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }

    /** @param array<string,mixed> $context */
    private function finishRestSpan(
        ?string $spanId,
        string $status,
        array $context
    ): void {
        if ($spanId === null || $this->profiler === null) {
            return;
        }
        $eventType = (string) ($context['event_type'] ?? '');
        if ($eventType === '') {
            throw new \LogicException(
                'OPUS_REST_PROFILER_EVENT_TYPE_MISSING'
            );
        }
        $safeContext = self::safeProfilerPayload($context);
        $this->profiler->event(
            'rest',
            $eventType,
            $safeContext,
            $status,
            $spanId
        );
        $this->profiler->endSpan(
            $spanId,
            $status,
            $safeContext
        );
    }

    /**
     * Recursively removes sensitive REST payload values from diagnostics while
     * retaining only their type and byte length for measured profiler panels.
     */
    private static function safeProfilerPayload(
        mixed $value,
        ?string $key = null
    ): mixed {
        $normalizedKey = strtolower(trim((string) $key));
        $sensitive = in_array(
            $normalizedKey,
            self::PROFILER_SENSITIVE_KEYS,
            true
        );
        if ($sensitive
            && !(
                in_array(
                    $normalizedKey,
                    ['staged', 'unstaged'],
                    true
                )
                && is_bool($value)
            )) {
            $summary = [
                'redacted' => true,
                'type' => get_debug_type($value),
            ];
            if (is_string($value)) {
                $summary['bytes'] = strlen($value);
            } elseif (is_array($value)) {
                $summary['items'] = count($value);
            }
            return $summary;
        }
        if (!is_array($value)) {
            return $value;
        }

        $safe = [];
        foreach ($value as $childKey => $childValue) {
            $safe[$childKey] = self::safeProfilerPayload(
                $childValue,
                is_string($childKey) ? $childKey : null
            );
        }
        return $safe;
    }

    /**
     * @param array<string,mixed> $actor
     * @return array{subject:string,roles:list<string>,provider:string}
     */
    private static function actor(array $actor): array
    {
        $subject = trim((string) ($actor['subject'] ?? ''));
        $provider = strtolower(trim((string) ($actor['provider'] ?? '')));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter(
                $actor['roles'],
                'is_string'
            )))
            : [];
        if ($subject === ''
            || strlen($subject) > 240
            || preg_match('/[\x00-\x1F\x7F]/', $subject) === 1
            || preg_match(
                '/^[a-z][a-z0-9._-]{0,63}$/D',
                $provider
            ) !== 1
            || $roles === []
            || count($roles) > 32) {
            throw new \RuntimeException(
                'OPUS_REST_API_ACTOR_INVALID'
            );
        }
        sort($roles, SORT_STRING);
        foreach ($roles as $role) {
            if (preg_match(
                '/^[a-z][a-z0-9._-]{0,63}$/D',
                $role
            ) !== 1) {
                throw new \RuntimeException(
                    'OPUS_REST_API_ACTOR_INVALID'
                );
            }
        }
        return [
            'subject' => $subject,
            'roles' => $roles,
            'provider' => $provider,
        ];
    }

    private function traceId(): ?string
    {
        $activeTrace = $this->profiler?->getActiveTrace();
        $traceId = $activeTrace?->getTraceId()
            ?? trim((string) getenv('OPUS_TRACE_ID'));
        if ($traceId === '') {
            return null;
        }
        if (preg_match('/^[a-f0-9]{16,64}$/D', $traceId) !== 1) {
            throw new \RuntimeException(
                'OPUS_REST_API_TRACE_ID_INVALID'
            );
        }
        return $traceId;
    }

    private static function profilerEnvironment(): bool
    {
        return in_array(
            strtolower(trim((string) getenv('OPUS_ENV'))),
            ['dev', 'local', 'development'],
            true
        );
    }

    private static function errorCode(\Throwable $cause): string
    {
        $message = trim($cause->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/D', $message) === 1
            ? $message
            : 'OPUS_REST_API_UNEXPECTED_FAILURE';
    }

    private static function canonical(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $actor,
        string $body
    ): string {
        return $method . "\n" . $path . "\n" . $timestamp . "\n"
            . $nonce . "\n" . hash('sha256', $actor) . "\n"
            . hash('sha256', $body);
    }

    private function secret(string $environment, string $type): string
    {
        return self::environmentValue(
            $environment,
            'OPUS_REST_API_CLIENT_' . $type . '_NOT_CONFIGURED'
        );
    }

    private static function environmentName(
        string $name,
        string $error
    ): string {
        $name = trim($name);
        if (preg_match('/^[A-Z][A-Z0-9_]{2,127}$/D', $name) !== 1) {
            throw new \RuntimeException($error);
        }
        return $name;
    }

    private static function environmentValue(
        string $name,
        string $error
    ): string {
        $value = getenv($name);
        if (!is_string($value) || strlen($value) < 8) {
            throw new \RuntimeException($error);
        }
        return $value;
    }

    private static function assertBaseUrl(string $url): void
    {
        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '');
        if (!is_array($parts)
            || !in_array(
                $parts['scheme'] ?? '',
                ['http', 'https'],
                true
            )
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || !in_array($path, ['', '/'], true)) {
            throw new \RuntimeException(
                'OPUS_REST_API_BASE_URL_INVALID'
            );
        }
    }

    private static function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new \RuntimeException(
                'OPUS_REST_API_CONFIG_PATH_INVALID'
            );
        }
        return $path;
    }
}
