<?php
declare(strict_types=1);

/**
 * Build the canonical OWASYS request context without owning dispatch.
 *
 * @param array<string,mixed> $server
 * @return array{method:string,path:string,mount:string,link:Closure,asset:Closure}
 */
return static function (array $server): array {
    $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
    $requestPath = parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
    $requestPath = '/' . trim($requestPath, '/');

    if ($requestPath === '/') {
        $path = '/';
        $mount = '';
    } elseif ($requestPath === '/owasys') {
        $path = '/';
        $mount = '/owasys';
    } elseif (str_starts_with($requestPath, '/owasys/')) {
        $path = substr($requestPath, strlen('/owasys'));
        $path = $path === '' ? '/' : $path;
        $mount = '/owasys';
    } else {
        $path = $requestPath;
        $mount = '';
    }

    $link = static fn (string $routePath): string => $mount . ($routePath === '/' ? '/' : '/' . ltrim($routePath, '/'));
    $asset = static fn (string $assetPath): string => $mount . '/' . ltrim($assetPath, '/');

    return [
        'method' => $method,
        'path' => $path,
        'mount' => $mount,
        'link' => $link,
        'asset' => $asset,
    ];
};
