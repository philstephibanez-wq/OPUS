<?php
declare(strict_types=1);

$publicRoot = __DIR__ . '/www';
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$requestPath = '/' . ltrim($requestPath, '/');

$candidate = realpath($publicRoot . $requestPath);
$publicReal = realpath($publicRoot);

if (
    $requestPath !== '/'
    && is_string($candidate)
    && is_string($publicReal)
    && str_starts_with(str_replace('\\', '/', $candidate), rtrim(str_replace('\\', '/', $publicReal), '/') . '/')
    && is_file($candidate)
    && strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== 'php'
) {
    return false;
}

require $publicRoot . '/index.php';
