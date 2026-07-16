<?php
declare(strict_types=1);

$publicRoot = dirname(__DIR__) . '/sites/owasys/www';
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';
$path = '/' . ltrim($path, '/');

$publicRootReal = realpath($publicRoot);
$staticCandidate = realpath($publicRoot . $path);
if (
    is_string($publicRootReal)
    && is_string($staticCandidate)
    && str_starts_with(str_replace('\\', '/', $staticCandidate), rtrim(str_replace('\\', '/', $publicRootReal), '/') . '/')
    && is_file($staticCandidate)
) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicRoot . '/index.php';
require $publicRoot . '/index.php';
return true;
