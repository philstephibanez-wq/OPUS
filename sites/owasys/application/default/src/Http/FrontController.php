<?php
declare(strict_types=1);

namespace Owasys\Application\Http;

use RuntimeException;

final class FrontController
{
    /** @var array<string,string> */
    private array $handlers;
    private string $applicationRoot;
    private string $defaultHandler;

    /** @param array<string,string> $handlers */
    public function __construct(string $applicationRoot, array $handlers, string $defaultHandler = 'score-page.php')
    {
        $applicationRoot = rtrim(str_replace('\\', '/', $applicationRoot), '/');
        if ($applicationRoot === '' || !is_dir($applicationRoot)) {
            throw new RuntimeException('OWASYS_APPLICATION_ROOT_INVALID');
        }

        foreach ($handlers as $path => $file) {
            if (!is_string($path) || !str_starts_with($path, '/') || !self::isSafeRelativeFile($file)) {
                throw new RuntimeException('OWASYS_APPLICATION_HANDLER_INVALID');
            }
        }
        if (!self::isSafeRelativeFile($defaultHandler)) {
            throw new RuntimeException('OWASYS_APPLICATION_DEFAULT_HANDLER_INVALID');
        }

        $this->applicationRoot = $applicationRoot;
        $this->handlers = $handlers;
        $this->defaultHandler = $defaultHandler;
    }

    /** @param array<string,mixed> $server */
    public function dispatch(array $server): void
    {
        $request = RequestContext::fromServer($server);
        $handler = $this->handlers[$request->path()] ?? null;

        if ($handler === null) {
            if ($request->method() !== 'GET') {
                http_response_code(405);
                header('Allow: GET');
                echo 'OWASYS_METHOD_NOT_SUPPORTED';
                return;
            }
            $handler = $this->defaultHandler;
        }

        $file = $this->applicationRoot . '/' . $handler;
        if (!is_file($file)) {
            throw new RuntimeException('OWASYS_APPLICATION_HANDLER_MISSING:' . $handler);
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

    private static function isSafeRelativeFile(mixed $file): bool
    {
        return is_string($file)
            && $file !== ''
            && !str_starts_with($file, '/')
            && !str_contains($file, '..')
            && preg_match('#^[A-Za-z0-9_./-]+\.php$#', $file) === 1;
    }
}
