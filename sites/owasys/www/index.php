<?php
declare(strict_types=1);

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

if ($requestPath === '/source-action.php' || $requestPath === '/owasys/source-action.php') {
    $siteRoot = dirname(__DIR__);
    $siteConfig = json_decode((string) file_get_contents($siteRoot . '/config/site.json'), true);
    $authConfig = is_array($siteConfig['auth'] ?? null) ? $siteConfig['auth'] : [];
    $sessionName = (string) ($authConfig['session_name'] ?? 'OWASYS_LOCAL_SESSION');
    if (session_status() === PHP_SESSION_NONE) {
        session_name($sessionName);
        session_start();
    }
    require $siteRoot . '/application/states/source/actions/source-browser-action.php';
    return;
}

if (PHP_SAPI === 'cli-server' && $requestPath !== '/' && !str_contains($requestPath, "\0")) {
    $publicRoot = __DIR__;
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

require __DIR__ . '/application.php';
