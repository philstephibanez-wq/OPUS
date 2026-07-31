<?php
declare(strict_types=1);

namespace Opus\Api\Rest;

use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Profiler\ProfilerInterface;

/** Secured server-side client for the OPUS resource-oriented REST API. */
final class RestClient implements RestClientInterface
{
    private function __construct(
        private readonly string $baseUrl,
        private readonly string $tokenEnvironment,
        private readonly string $hmacEnvironment,
        private readonly int $timeoutSeconds,
        private readonly int $maxResponseBytes,
        private readonly ?ProfilerInterface $profiler
    ) {
    }

    public static function fromConfig(
        string $configFile,
        ?ProfilerInterface $profiler = null
    ): self {
        $config = StructuredFileLoader::instance()->read($configFile);
        if (($config['contract'] ?? null) !== 'OPUS_REST_API_CLIENT_CONFIG_V1') {
            throw new \RuntimeException('OPUS_REST_API_CLIENT_CONFIG_CONTRACT_INVALID');
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
        $maximum = (int) ($config['max_response_bytes'] ?? 0);
        if ($timeout < 1 || $timeout > 600) {
            throw new \RuntimeException('OPUS_REST_API_TIMEOUT_INVALID');
        }
        if ($maximum < 4096 || $maximum > 16777216) {
            throw new \RuntimeException('OPUS_REST_API_RESPONSE_LIMIT_INVALID');
        }
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
            $maximum,
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
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new \RuntimeException('OPUS_REST_API_METHOD_INVALID');
        }
        $resource = '/' . ltrim($resource, '/');
        if (!str_starts_with($resource, '/api/v1/')
            || str_contains($resource, "\0")
            || str_contains($resource, '..')) {
            throw new \RuntimeException('OPUS_REST_API_RESOURCE_INVALID');
        }
        if ($method === 'GET' && $body !== []) {
            throw new \RuntimeException('OPUS_REST_API_GET_BODY_FORBIDDEN');
        }

        $json = $body === [] ? '' : Json::instance()->encode(['data' => $body]);
        $spanId = $this->beginRestSpan($method, $resource, strlen($json));
        $status = 0;
        $responseBytes = 0;
        try {
            $actorHeader = rtrim(strtr(
                base64_encode(Json::instance()->encode($actor, false)),
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
                'Authorization: Bearer ' . $this->secret($this->tokenEnvironment, 'TOKEN'),
                'Content-Type: application/json',
                'X-Opus-Rest-Timestamp: ' . $timestamp,
                'X-Opus-Rest-Nonce: ' . $nonce,
                'X-Opus-Rest-Signature: ' . $signature,
                'X-Opus-Rest-Actor: ' . $actorHeader,
            ];
            $traceId = $this->traceId();
            if ($traceId !== null) {
                $headers[] = 'X-Opus-Trace-Id: ' . $traceId;
            }

            $context = stream_context_create(['http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $json,
                'ignore_errors' => true,
                'timeout' => $this->timeoutSeconds,
            ]]);
            $response = @file_get_contents($this->baseUrl . $resource, false, $context);
            if (!is_string($response)) {
                throw new \RuntimeException('OPUS_REST_API_TRANSPORT_FAILED');
            }
            $responseBytes = strlen($response);
            if ($responseBytes > $this->maxResponseBytes) {
                throw new \RuntimeException('OPUS_REST_API_RESPONSE_TOO_LARGE');
            }
            $status = self::status($http_response_header ?? []);
            $decoded = Json::instance()->parse($response, $resource);
            if ($status < 200 || $status >= 300) {
                $code = trim((string) ($decoded['error_code'] ?? ''));
                throw new \RuntimeException(
                    preg_match('/^[A-Z0-9_:-]{3,240}$/', $code) === 1
                        ? $code
                        : 'OPUS_REST_API_REQUEST_FAILED'
                );
            }
            $data = $decoded['data'] ?? null;
            if (!is_array($data)) {
                throw new \RuntimeException('OPUS_REST_API_RESPONSE_DATA_INVALID');
            }
            $this->finishRestSpan($spanId, 'success', [
                'event_type' => 'rest.response.received',
                'http_status' => $status,
                'response_bytes' => $responseBytes,
            ]);

            return $data;
        } catch (\Throwable $cause) {
            $this->finishRestSpan($spanId, 'error', [
                'event_type' => 'rest.request.failed',
                'http_status' => $status > 0 ? $status : null,
                'response_bytes' => $responseBytes,
                'error_code' => self::errorCode($cause),
                'exception_class' => $cause::class,
            ]);
            throw $cause;
        }
    }

    /** @param list<string> $headers */
    private static function status(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match) === 1) {
                return (int) $match[1];
            }
        }
        return 0;
    }

    private function beginRestSpan(string $method, string $resource, int $requestBytes): ?string
    {
        if ($this->profiler === null || $this->profiler->getActiveTrace() === null) {
            return null;
        }
        return $this->profiler->beginSpan('rest', 'rest.request.started', [
            'event_type' => 'rest.request.started',
            'target_service' => (string) parse_url($this->baseUrl, PHP_URL_HOST),
            'method' => $method,
            'route' => $resource,
            'request_bytes' => $requestBytes,
        ]);
    }

    /** @param array<string,mixed> $context */
    private function finishRestSpan(?string $spanId, string $status, array $context): void
    {
        if ($spanId === null || $this->profiler === null) {
            return;
        }
        $eventType = (string) ($context['event_type'] ?? '');
        if ($eventType === '') {
            throw new \LogicException('OPUS_REST_PROFILER_EVENT_TYPE_MISSING');
        }
        $this->profiler->event(
            'rest',
            $eventType,
            $context,
            $status,
            $spanId
        );
        $this->profiler->endSpan($spanId, $status, $context);
    }

    private function traceId(): ?string
    {
        $activeTrace = $this->profiler?->getActiveTrace();
        $traceId = $activeTrace?->getTraceId() ?? trim((string) getenv('OPUS_TRACE_ID'));
        if ($traceId === '') {
            return null;
        }
        if (preg_match('/^[a-f0-9]{16,64}$/D', $traceId) !== 1) {
            throw new \RuntimeException('OPUS_REST_API_TRACE_ID_INVALID');
        }
        return $traceId;
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

    private static function environmentName(string $name, string $error): string
    {
        $name = trim($name);
        if (preg_match('/^[A-Z][A-Z0-9_]{2,127}$/', $name) !== 1) {
            throw new \RuntimeException($error);
        }
        return $name;
    }

    private static function environmentValue(string $name, string $error): string
    {
        $value = getenv($name);
        if (!is_string($value) || strlen($value) < 8) {
            throw new \RuntimeException($error);
        }
        return $value;
    }

    private static function assertBaseUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new \RuntimeException('OPUS_REST_API_BASE_URL_INVALID');
        }
    }
}
