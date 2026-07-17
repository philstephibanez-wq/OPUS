<?php
declare(strict_types=1);

namespace Owasys\Application\Http;

use RuntimeException;

final class FrontController
{
    /** @var array<string,string> */
    private array $handlers;
    private string $httpRoot;

    /** @param array<string,string> $handlers */
    public function __construct(string $httpRoot, array $handlers)
    {
        $httpRoot = rtrim(str_replace('\\', '/', $httpRoot), '/');
        if ($httpRoot === '' || !is_dir($httpRoot)) {
            throw new RuntimeException('OWASYS_HTTP_ROOT_INVALID');
        }

        foreach ($handlers as $path => $file) {
            if (!is_string($path) || !str_starts_with($path, '/') || !is_string($file) || $file === '' || str_contains($file, '..') || str_contains($file, '/')) {
                throw new RuntimeException('OWASYS_HTTP_HANDLER_INVALID');
            }
        }

        $this->httpRoot = $httpRoot;
        $this->handlers = $handlers;
    }

    /** @param array<string,mixed> $server */
    public function dispatch(array $server): void
    {
        $path = self::normalizePath((string) ($server['REQUEST_URI'] ?? '/'));
        $handler = $this->handlers[$path] ?? 'application.php';
        $file = $this->httpRoot . '/' . $handler;

        if (!is_file($file)) {
            throw new RuntimeException('OWASYS_HTTP_HANDLER_MISSING:' . $handler);
        }

        require $file;
    }

    public static function normalizePath(string $requestUri): string
    {
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = is_string($path) ? '/' . ltrim(rawurldecode($path), '/') : '/';

        if ($path === '/owasys') {
            return '/';
        }
        if (str_starts_with($path, '/owasys/')) {
            $path = substr($path, strlen('/owasys'));
        }

        return $path === '' ? '/' : $path;
    }
}
