<?php
declare(strict_types=1);

$publicRoot = __DIR__ . '/www';
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

if ($requestPath !== '/' && !str_contains($requestPath, "\0")) {
    $candidate = realpath($publicRoot . '/' . ltrim($requestPath, '/'));
    $publicRootReal = realpath($publicRoot);

    if (
        is_string($candidate)
        && is_string($publicRootReal)
        && str_starts_with($candidate, $publicRootReal . DIRECTORY_SEPARATOR)
        && is_file($candidate)
        && strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== 'php'
    ) {
        return false;
    }
}

require $publicRoot . '/index.php';
