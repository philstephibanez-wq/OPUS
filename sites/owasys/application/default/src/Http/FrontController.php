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
        $request = RequestContext::fromServer($server);
        $handler = $this->handlers[$request->path()] ?? 'application.php';
        $file = $this->httpRoot . '/' . $handler;

        if (!is_file($file)) {
            throw new RuntimeException('OWASYS_HTTP_HANDLER_MISSING:' . $handler);
        }

        require $file;
    }

    public static function normalizePath(string $requestUri): string
    {
        return RequestContext::fromServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $requestUri,
        ])->path();
    }
}
