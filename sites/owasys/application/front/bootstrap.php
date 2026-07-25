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
    'application/shared/RuntimeInterface.php',
    'application/shared/Application.php',
    'application/front/default/models/AuthSession.php',
    'application/front/default/services/RuntimeSecurity.php',
    'application/front/default/services/LocaleRegistry.php',
    'application/front/default/services/NavigationBuilder.php',
    'application/front/default/services/FsmMermaidBuilder.php',
    'application/front/default/services/ScorePageRenderer.php',
    'application/front/modules/registry/services/ApplicationSingletonInspector.php',
    'application/front/modules/registry/repositories/RegistryRepository.php',
    'application/front/modules/registry/models/RegistryModel.php',
    'application/front/modules/registry/controllers/RegistryController.php',
    'application/front/modules/creation/models/ApplicationCreationModel.php',
    'application/front/modules/creation/controllers/CreationController.php',
    'application/front/default/services/FsmActionHandlers.php',
    'application/front/default/controllers/RuntimeController.php',
    'application/front/Runtime.php',
];

$fileBoundary = File::instance();
foreach ($files as $relative) {
    $path = $siteRoot . '/' . $relative;
    if (!$fileBoundary->exists($path)) {
        throw new RuntimeException(
            'OWASYS_FRONT_COMPONENT_MISSING:' . $relative
        );
    }
    require_once $path;
}

OwasysApplication::instance(
    $siteRoot,
    new OwasysFrontRuntime($siteRoot, $siteConfig)
)->run();

return true;
