<?php
declare(strict_types=1);

use Opus\Assets\FrameworkAssetResponder;
use Opus\File\File;
use Opus\File\StructuredFileLoader;

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));

if (PHP_SAPI === 'cli-server') {
    $sourceFingerprint = trim((string) getenv(
        'OPUS_DEV_SERVER_SOURCE_FINGERPRINT'
    ));
    if (preg_match('/^[a-f0-9]{20}$/D', $sourceFingerprint) === 1) {
        header(
            'X-Opus-Source-Fingerprint: ' . $sourceFingerprint
        );
    }
    header('X-Owasys-Fsm-Ui-Contract: signal-driven-a4k');

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
            && $candidate !== $frontController
            && str_starts_with(
                str_replace('\\', '/', $candidate),
                rtrim(str_replace('\\', '/', $publicRoot), '/') . '/'
            )
            && is_file($candidate)) {
            return false;
        }
    }
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $siteRoot . '/www/index.php';
}
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException(
        'OWASYS_FRONT_COMPOSER_AUTOLOAD_MISSING'
    );
}
require_once $autoload;
if (FrameworkAssetResponder::serveCurrentRequest($opusRoot)) {
    return true;
}
$files = [
    'application/default/models/AuthSession.php',
    'application/default/services/SessionRuntimeInterface.php',
    'application/default/services/SessionRuntime.php',
    'application/default/services/RuntimeSecurity.php',
    'application/default/services/LocaleRegistry.php',
    'application/default/services/FsmGuardHandlers.php',
    'application/default/services/NavigationBuilder.php',
    'application/default/services/FsmDiagramBuilder.php',
    'application/default/services/FsmDesignerGateway.php',
    'application/default/services/ScorePageRenderer.php',
    'application/registry/models/RegistryModel.php',
    'application/registry/controllers/RegistryController.php',
    'application/creation/models/ApplicationCreationModel.php',
    'application/creation/controllers/CreationController.php',
    'application/source/models/SourceModel.php',
    'application/fsm/models/ApplicationFsmModel.php',
    'application/source/controllers/SourceController.php',
    'application/security/controllers/SecurityController.php',
    'application/default/services/FsmActionHandlers.php',
    'application/default/services/FsmHandlerCatalog.php',
    'application/default/services/FsmMenuSignalGateway.php',
    'application/default/controllers/RuntimeController.php',
    'application/default/ApplicationInterface.php',
    'application/default/Application.php',
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
OwasysFrontApplication::instance($siteRoot)->run();
return true;
