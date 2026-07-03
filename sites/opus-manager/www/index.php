<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__);
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');
if ($path === '/') {
    $controller = 'home';
} else {
    $controller = preg_replace('/\.php$/', '', basename($path)) ?: 'home';
}
if (!preg_match('/^[A-Za-z0-9_-]+$/', $controller)) {
    http_response_code(400);
    echo 'OPUS_CONTROLLER_INVALID';
    exit;
}
$legacy = $siteRoot . '/application/' . $controller . '/views/legacy-public-entry.php';
if (!is_file($legacy)) {
    http_response_code(404);
    echo 'OPUS_ROUTE_NOT_FOUND: ' . htmlspecialchars($controller, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}
require $legacy;