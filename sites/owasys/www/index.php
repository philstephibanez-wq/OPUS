<?php
declare(strict_types=1);

use Opus\Assets\FrameworkAssetResponder;
use Opus\File\StructuredFileLoader;

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url(
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        PHP_URL_PATH
    );
    $requestPath = is_string($requestPath)
        ? rawurldecode($requestPath)
        : '/';

    if ($requestPath !== '/' && !str_contains($requestPath, "\0")) {
        $publicRoot = realpath(__DIR__);
        $candidate = realpath(
            __DIR__
            . DIRECTORY_SEPARATOR
            . ltrim(
                str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $requestPath
                ),
                DIRECTORY_SEPARATOR
            )
        );
        $frontController = realpath(__FILE__);

        if (
            $publicRoot !== false
            && $candidate !== false
            && $candidate !== $frontController
        ) {
            $prefix = rtrim(
                str_replace('\\', '/', $publicRoot),
                '/'
            ) . '/';

            if (
                str_starts_with(
                    str_replace('\\', '/', $candidate),
                    $prefix
                )
                && is_file($candidate)
            ) {
                return false;
            }
        }
    }

    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
}

$siteRoot = dirname(__DIR__);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    exit('OWASYS_COMPOSER_AUTOLOAD_MISSING');
}

require_once $autoload;

if (FrameworkAssetResponder::serveCurrentRequest($opusRoot)) {
    exit;
}

$siteConfigFile = $siteRoot . '/config/site.json';
try {
    $siteConfig = StructuredFileLoader::instance()->read($siteConfigFile);
} catch (Throwable $error) {
    http_response_code(500);
    throw new RuntimeException(
        'OWASYS_SITE_CONFIG_INVALID:' . $error->getMessage(),
        0,
        $error
    );
}

$files = [
    'application/default/models/AuthSession.php',
    'application/default/services/RuntimeSecurity.php',
    'application/default/services/LocaleRegistry.php',
    'application/default/services/NavigationBuilder.php',
    'application/default/services/FsmMermaidBuilder.php',
    'application/default/services/ScorePageRenderer.php',
    'application/registry/repositories/RegistryRepository.php',
    'application/registry/models/RegistryModel.php',
    'application/registry/controllers/RegistryController.php',
    'application/default/services/FsmActionHandlers.php',
    'application/default/controllers/RuntimeController.php',
];

foreach ($files as $relative) {
    $file = $siteRoot . '/' . $relative;

    if (!is_file($file)) {
        http_response_code(500);
        exit('OWASYS_RUNTIME_COMPONENT_MISSING:' . $relative);
    }

    require_once $file;
}

$session = new OwasysAuthSession();
$security = new OwasysRuntimeSecurity($siteRoot, $siteConfig);
$runtime = new OwasysRuntimeController(
    $siteRoot,
    $siteConfig,
    $session,
    $security,
    new OwasysScorePageRenderer($siteRoot)
);
$runtime->run();
