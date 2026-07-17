<?php
declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = is_string($path) ? '/' . ltrim(rawurldecode($path), '/') : '/';
if (str_starts_with($path, '/owasys/')) {
    $path = substr($path, strlen('/owasys'));
} elseif ($path === '/owasys') {
    $path = '/';
}

$handlers = [
    '/build-action.php' => 'build-action.php',
    '/source-action.php' => 'source-action.php',
    '/structure-preview.php' => 'structure-preview.php',
];

if (isset($handlers[$path])) {
    require dirname(__DIR__) . '/application/default/http/' . $handlers[$path];
    return;
}

require dirname(__DIR__) . '/application/default/http/application.php';
