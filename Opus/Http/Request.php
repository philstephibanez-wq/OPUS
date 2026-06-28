<?php
declare(strict_types=1);

namespace Opus\Http;

use Opus\Foundation\Support;

/**
 * HTTP request value object used by the OPUS runtime.
 *
 * Carries the resolved method, URI, query parameters and POST data consumed by routing and application dispatch.
 */
final class Request
 implements RequestInterface {
    public string $host;
    public string $method;
    public string $basePath;
    public string $path;
    /** @var list<string> */
    public array $segments;
    private string $body;

    private function __construct(string $host, string $method, string $basePath, string $path, string $body = '')
    {
        $this->host = $host;
        $this->method = strtoupper($method);
        $this->basePath = $basePath;
        $this->path = $path;
        $this->segments = $path === '' ? [] : array_values(array_filter(explode('/', $path), static fn($v) => $v !== ''));
        $this->body = $body;
    }

    public static function fromParts(string $host, string $method, string $basePath, string $path, string $body = ''): self
    {
        return new self($host, $method, $basePath, trim($path, '/'), $body);
    }

    public static function fromGlobals(string $rootDir): self
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1'));
        $host = preg_replace('/:\\d+$/', '', $host) ?? $host;
        $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $body = file_get_contents('php://input');
        $body = is_string($body) ? $body : '';

        $rawPath = parse_url($requestUri, PHP_URL_PATH);
        $rawPath = is_string($rawPath) ? rawurldecode($rawPath) : '/';
        $rawPath = Support::normalizePath('/' . ltrim($rawPath, '/'));

        $basePath = Support::normalizePath(str_replace('\\', '/', dirname($scriptName)));
        if ($basePath === '.' || $basePath === '/') {
            $basePath = '';
        }

        if ($basePath !== '' && Support::startsWith($rawPath, $basePath)) {
            $path = substr($rawPath, strlen($basePath));
        } else {
            $path = $rawPath;
        }

        $path = Support::trimSlashes($path);

        return new self($host, $method, $basePath, $path, $body);
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,mixed> */
    public function jsonBody(): array
    {
        if (trim($this->body) === '') {
            return [];
        }

        $decoded = json_decode($this->body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OPUS_HTTP_REQUEST_JSON_BODY_INVALID');
        }

        return $decoded;
    }
}
