<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT', realpath(__DIR__ . '/..'));
define('ENV', getenv('OPUS_ENV') ?: 'dev');

function opus_serve_package_asset(): void {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path)) { return; }
    $path = rawurldecode($path);

    $assetMarker = strpos($path, '/_site/');
    if ($assetMarker === false) {
        return;
    }
    $assetPath = substr($path, $assetMarker);
    if (!preg_match('#^/_site/([A-Za-z0-9_-]+)/(.*)$#', $assetPath, $m)) {
        return;
    }

    $site = $m[1];
    $asset = str_replace('\\', '/', $m[2]);
    $asset = ltrim($asset, '/');
    if ($asset === '' || strpos($asset, '..') !== false) {
        http_response_code(400);
        echo 'Bad asset path';
        exit;
    }

    $publicRoot = realpath(ROOT . '/sites/' . $site . '/www');
    if (!$publicRoot) {
        http_response_code(404);
        echo 'Site asset root not found';
        exit;
    }

    $file = realpath($publicRoot . '/' . $asset);
    if (!$file || strpos(str_replace('\\', '/', $file), str_replace('\\', '/', $publicRoot) . '/') !== 0 || !is_file($file)) {
        http_response_code(404);
        echo 'Asset not found';
        exit;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = array(
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    );
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: no-store, max-age=0');
    readfile($file);
    exit;
}

opus_serve_package_asset();

require_once ROOT . '/Opus/Bootstrap.php';
require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';

$app = OPUS_Application::getInstance();
$app->run();
