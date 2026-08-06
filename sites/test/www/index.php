<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url(
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        PHP_URL_PATH
    );
    if (is_string($requestPath) && !str_contains($requestPath, "\0")) {
        $publicRoot = str_replace('\\', '/', __DIR__);
        $candidate = realpath(
            __DIR__ . '/' . ltrim(rawurldecode($requestPath), '/')
        );
        if (is_string($candidate)) {
            $candidate = str_replace('\\', '/', $candidate);
            if (str_starts_with($candidate, $publicRoot . '/')
                && is_file($candidate)) {
                return false;
            }
        }
    }
}

require dirname(__DIR__) . '/application/default/bootstrap.php';