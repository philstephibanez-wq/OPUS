<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$decodedPath = rawurldecode($rawPath);
$path = $decodedPath === '/' ? '/' : rtrim($decodedPath, '/');

$nativeRoute = p7ops_resolve_native_route($path);
if ($nativeRoute !== null) {
    $_GET['lang'] = (string) $nativeRoute['lang'];
    $_GET['site'] = $_GET['site'] ?? 'site-alpha';
    $path = (string) $nativeRoute['canonical'];
}

$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if ($path !== '/' && is_file($file)) {
    return false;
}

if ($path === '/opus-lstsar-manager' || $path === '/opus-lstsar-manager/operations') {
    require __DIR__ . '/index.php';
    return true;
}

if ($path === '/opus-lstsar-manager/action') {
    require __DIR__ . '/action.php';
    return true;
}

if ($path === '/opus-lstsar-manager/command' || $path === '/opus-lstsar-manager/command-center') {
    require __DIR__ . '/command.php';
    return true;
}

if ($path === '/opus-lstsar-manager/navigation' || $path === '/opus-lstsar-manager/navigation-polish') {
    require __DIR__ . '/navigation.php';
    return true;
}

if ($path === '/opus-lstsar-manager/diagnostics' || $path === '/opus-lstsar-manager/runtime-diagnostics') {
    require __DIR__ . '/diagnostics.php';
    return true;
}

if ($path === '/opus-lstsar-manager/health' || $path === '/opus-lstsar-manager/health-hub') {
    require __DIR__ . '/health.php';
    return true;
}

require __DIR__ . '/index.php';
return true;