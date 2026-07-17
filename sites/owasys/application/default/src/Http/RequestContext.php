<?php
declare(strict_types=1);

namespace Owasys\Application\Http;

final class RequestContext
{
    private string $method;
    private string $path;
    private string $mount;

    private function __construct(string $method, string $path, string $mount)
    {
        $this->method = $method;
        $this->path = $path;
        $this->mount = $mount;
    }

    /** @param array<string,mixed> $server */
    public static function fromServer(array $server): self
    {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $requestPath = parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
        $requestPath = '/' . trim($requestPath, '/');

        if ($requestPath === '/') {
            return new self($method, '/', '');
        }
        if ($requestPath === '/owasys') {
            return new self($method, '/', '/owasys');
        }
        if (str_starts_with($requestPath, '/owasys/')) {
            $path = substr($requestPath, strlen('/owasys'));
            return new self($method, $path === '' ? '/' : $path, '/owasys');
        }

        return new self($method, $requestPath, '');
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function mount(): string
    {
        return $this->mount;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function link(string $routePath): string
    {
        return $this->mount . ($routePath === '/' ? '/' : '/' . ltrim($routePath, '/'));
    }

    public function asset(string $assetPath): string
    {
        return $this->mount . '/' . ltrim($assetPath, '/');
    }
}
