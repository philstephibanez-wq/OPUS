<?php
declare(strict_types=1);

namespace Opus\Rcp\Rest;

use Opus\File\Json;
use Opus\File\StructuredFileLoader;

/**
 * Generic server-side client for a secured OPUS REST/Composer backend.
 *
 * Secrets remain server-side. The complete JSON envelope is authenticated by
 * bearer and HMAC credentials configured through environment variables.
 */
final class RcpRestClient implements RcpRestClientInterface
{
    private function __construct(
        private readonly string $endpoint,
        private readonly string $tokenEnvironment,
        private readonly string $hmacEnvironment,
        private readonly int $timeoutSeconds,
        private readonly int $maxResponseBytes
    ) {
    }

    public static function fromConfig(string $configFile): self
    {
        $config = StructuredFileLoader::instance()->read($configFile);
        if (($config['contract'] ?? null)
            !== 'OPUS_RCP_REST_CLIENT_CONFIG_V1') {
            throw new \RuntimeException(
                'OPUS_RCP_CLIENT_CONFIG_CONTRACT_INVALID'
            );
        }

        $endpoint = trim((string) ($config['endpoint'] ?? ''));
        self::assertEndpoint($endpoint);
        $tokenEnvironment = self::environmentName(
            (string) ($config['token_env'] ?? ''),
            'OPUS_RCP_TOKEN_ENV_INVALID'
        );
        $hmacEnvironment = self::environmentName(
            (string) ($config['hmac_env'] ?? ''),
            'OPUS_RCP_HMAC_ENV_INVALID'
        );

        $timeout = (int) ($config['timeout_seconds'] ?? 0);
        $maximum = (int) ($config['max_response_bytes'] ?? 0);
        if ($timeout < 1 || $timeout > 600) {
            throw new \RuntimeException('OPUS_RCP_TIMEOUT_INVALID');
        }
        if ($maximum < 4096 || $maximum > 16777216) {
            throw new \RuntimeException(
                'OPUS_RCP_RESPONSE_LIMIT_INVALID'
            );
        }

        return new self(
            $endpoint,
            $tokenEnvironment,
            $hmacEnvironment,
            $timeout,
            $maximum
        );
    }

    public function execute(
        string $operation,
        array $parameters,
        array $actor
    ): array {
        if (preg_match('/^[a-z][a-z0-9.-]*$/', $operation) !== 1) {
            throw new \RuntimeException('OPUS_RCP_OPERATION_INVALID');
        }

        $token = $this->secret($this->tokenEnvironment, 'TOKEN');
        $hmacSecret = $this->secret($this->hmacEnvironment, 'HMAC');
        $executionId = bin2hex(random_bytes(16));
        $request = [
            'contract' => 'OPUS_RCP_REST_EXECUTION_REQUEST_V1',
            'execution_id' => $executionId,
            'operation' => $operation,
            'actor' => $this->actor($actor),
            'parameters' => $parameters,
            'requested_at_utc' => gmdate('c'),
            'expires_at_utc' => gmdate('c', time() + 120),
        ];

        $encoded = Json::instance()->encode($request, false);
        $timestamp = (string) time();
        $nonce = $executionId;
        $path = (string) (parse_url($this->endpoint, PHP_URL_PATH) ?: '/');
        $signature = hash_hmac(
            'sha256',
            $this->canonical('POST', $path, $timestamp, $nonce, $encoded),
            $hmacSecret
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $token,
                    'X-Opus-Rcp-Timestamp: ' . $timestamp,
                    'X-Opus-Rcp-Nonce: ' . $nonce,
                    'X-Opus-Rcp-Signature: ' . $signature,
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Accept-Language: ' . $this->localeHeader(),
                    'Content-Length: ' . strlen($encoded),
                ]),
                'content' => $encoded,
            ],
        ]);

        $stream = @fopen($this->endpoint, 'rb', false, $context);
        unset($token, $hmacSecret, $signature, $request, $parameters);
        if ($stream === false) {
            throw new \RuntimeException('OPUS_RCP_CONNECTION_FAILED');
        }

        try {
            $response = stream_get_contents(
                $stream,
                $this->maxResponseBytes + 1
            );
            if (!is_string($response)) {
                throw new \RuntimeException(
                    'OPUS_RCP_RESPONSE_READ_FAILED'
                );
            }
            if (strlen($response) > $this->maxResponseBytes) {
                throw new \RuntimeException(
                    'OPUS_RCP_RESPONSE_LIMIT_EXCEEDED'
                );
            }
        } finally {
            fclose($stream);
        }

        $headers = isset($http_response_header)
            && is_array($http_response_header)
            ? $http_response_header
            : [];
        $status = self::httpStatus($headers);
        $contentType = self::headerValue($headers, 'content-type');
        $decoded = null;

        if (self::jsonContentType($contentType)) {
            try {
                $decoded = Json::instance()->parse(
                    $response,
                    $this->endpoint
                );
            } catch (\Throwable $cause) {
                throw new \RuntimeException(
                    $status >= 400
                        ? 'OPUS_RCP_BACKEND_JSON_INVALID:' . $status
                        : 'OPUS_RCP_RESPONSE_JSON_INVALID',
                    0,
                    $cause
                );
            }
        }

        if ($status < 200 || $status >= 300) {
            $code = is_array($decoded)
                ? trim((string) ($decoded['error_code'] ?? ''))
                : '';
            if (preg_match('/^[A-Z0-9_:-]{3,240}$/', $code) === 1) {
                throw new \RuntimeException($code);
            }
            throw new \RuntimeException(
                'OPUS_RCP_BACKEND_HTTP_ERROR:' . $status
            );
        }
        if (!is_array($decoded)) {
            throw new \RuntimeException(
                'OPUS_RCP_RESPONSE_CONTENT_TYPE_INVALID'
            );
        }
        if (($decoded['contract'] ?? null)
            !== 'OPUS_RCP_REST_EXECUTION_V1') {
            throw new \RuntimeException(
                'OPUS_RCP_RESPONSE_CONTRACT_INVALID'
            );
        }
        if (($decoded['execution_id'] ?? null) !== $executionId) {
            throw new \RuntimeException(
                'OPUS_RCP_EXECUTION_ID_MISMATCH'
            );
        }
        if (($decoded['status'] ?? null) !== 'succeeded') {
            $code = trim((string) (
                $decoded['error_code'] ?? 'OPUS_RCP_COMMAND_FAILED'
            ));
            throw new \RuntimeException(
                preg_match('/^[A-Z0-9_:-]{3,240}$/', $code) === 1
                    ? $code
                    : 'OPUS_RCP_COMMAND_FAILED'
            );
        }

        return is_array($decoded['result'] ?? null)
            ? $decoded['result']
            : ['value' => $decoded['result'] ?? null];
    }

    /** @param list<string> $headers */
    private static function httpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (!is_string($header)) {
                continue;
            }
            if (preg_match('/^HTTP\/\S+\s+(\d{3})(?:\s|$)/i', $header, $match) === 1) {
                return (int) $match[1];
            }
        }
        throw new \RuntimeException('OPUS_RCP_HTTP_STATUS_MISSING');
    }

    /** @param list<string> $headers */
    private static function headerValue(array $headers, string $name): string
    {
        $prefix = strtolower($name) . ':';
        foreach ($headers as $header) {
            if (!is_string($header)) {
                continue;
            }
            if (str_starts_with(strtolower($header), $prefix)) {
                return trim(substr($header, strlen($prefix)));
            }
        }
        return '';
    }

    private static function jsonContentType(string $contentType): bool
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
        return $mediaType === 'application/json'
            || str_ends_with($mediaType, '+json');
    }

    /** @param array<string,mixed> $actor */
    private function actor(array $actor): array
    {
        $subject = trim((string) ($actor['subject'] ?? $actor['id'] ?? ''));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter(
                $actor['roles'],
                'is_string'
            )))
            : [];
        $provider = trim((string) ($actor['provider'] ?? ''));
        if ($subject === '' || $roles === [] || $provider === '') {
            throw new \RuntimeException('OPUS_RCP_ACTOR_INVALID');
        }
        return [
            'subject' => $subject,
            'roles' => $roles,
            'provider' => $provider,
        ];
    }

    private function secret(string $environment, string $type): string
    {
        $secret = getenv($environment);
        if (!is_string($secret) || strlen($secret) < 32) {
            throw new \RuntimeException(
                'OPUS_RCP_CLIENT_' . $type . '_NOT_CONFIGURED'
            );
        }
        return $secret;
    }

    private function canonical(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $body
    ): string {
        return strtoupper($method) . "\n"
            . '/' . ltrim($path, '/') . "\n"
            . $timestamp . "\n"
            . $nonce . "\n"
            . hash('sha256', $body);
    }

    private function localeHeader(): string
    {
        return is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null)
            ? $_SERVER['HTTP_ACCEPT_LANGUAGE']
            : 'fr-FR';
    }

    private static function environmentName(
        string $value,
        string $error
    ): string {
        $value = trim($value);
        if (preg_match('/^[A-Z][A-Z0-9_]{2,127}$/', $value) !== 1) {
            throw new \RuntimeException($error);
        }
        return $value;
    }

    private static function assertEndpoint(string $endpoint): void
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts)) {
            throw new \RuntimeException('OPUS_RCP_ENDPOINT_INVALID');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $local = in_array(
            $host,
            ['127.0.0.1', 'localhost', '::1'],
            true
        );

        if (!in_array($scheme, ['https', 'http'], true)
            || ($scheme === 'http' && !$local)
            || $path === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new \RuntimeException('OPUS_RCP_ENDPOINT_INVALID');
        }
    }
}
