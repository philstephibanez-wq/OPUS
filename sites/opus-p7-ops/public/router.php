<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $path === '/' ? '/' : rtrim($path, '/');

if ($path === '/opus-lstsar-manager/action') {
    require __DIR__ . '/action.php';
    return true;
}

$requestedFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if ($path !== '/' && is_file($requestedFile)) {
    return false;
}

require __DIR__ . '/index.php';
return true;