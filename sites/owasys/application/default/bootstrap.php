<?php
declare(strict_types=1);

use Opus\Assets\FrameworkAssetResponder;
use Opus\File\File;
use Opus\File\StructuredFileLoader;

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url(
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        PHP_URL_PATH
    );
    $requestPath = is_string($requestPath)
        ? rawurldecode($requestPath)
        : '/';

    if ($requestPath !== '/' && !str_contains($requestPath, "\0")) {
        $publicRoot = realpath($siteRoot . '/www');
        $candidate = realpath(
            $siteRoot . '/www/' . ltrim($requestPath, '/')
        );
        $frontController = realpath($siteRoot . '/www/index.php');

        if ($publicRoot !== false
            && $candidate !== false
            && $candidate !== $frontController) {
            $prefix = rtrim(str_replace('\\', '/', $publicRoot), '/') . '/';
            if (str_starts_with(
                str_replace('\\', '/', $candidate),
                $prefix
            ) && is_file($candidate)) {
                return false;
            }
        }
    }

    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $siteRoot . '/www/index.php';
}

$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    $stream = fopen('php://output', 'wb');
    if ($stream !== false) {
        fwrite($stream, 'OWASYS_COMPOSER_AUTOLOAD_MISSING');
        fclose($stream);
    }
    return true;
}

require_once $autoload;

if (FrameworkAssetResponder::serveCurrentRequest($opusRoot)) {
    return true;
}

$siteConfig = StructuredFileLoader::instance()->read(
    $siteRoot . '/config/site.json'
);

$files = [
    'application/default/models/AuthSession.php',
    'application/default/services/RuntimeSecurity.php',
    'application/default/services/LocaleRegistry.php',
    'application/default/services/NavigationBuilder.php',
    'application/default/services/FsmMermaidBuilder.php',
    'application/default/services/ScorePageRenderer.php',
    'application/registry/services/ApplicationSingletonInspector.php',
    'application/registry/repositories/RegistryRepository.php',
    'application/registry/models/RegistryModel.php',
    'application/registry/controllers/RegistryController.php',
    'application/default/services/FsmActionHandlers.php',
    'application/default/controllers/RuntimeController.php',
    'application/api/controllers/BackendApiController.php',
    'application/default/Application.php',
];

$fileBoundary = File::instance();
foreach ($files as $relative) {
    $path = $siteRoot . '/' . $relative;
    if (!$fileBoundary->exists($path)) {
        throw new RuntimeException(
            'OWASYS_RUNTIME_COMPONENT_MISSING:' . $relative
        );
    }
    require_once $path;
}

OwasysApplication::instance($siteRoot, $siteConfig)->run();

return true;
