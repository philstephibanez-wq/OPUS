<?php
declare(strict_types=1);

use Opus\File\File;
use Opus\File\StructuredFileLoader;

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));
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

StructuredFileLoader::instance()->read(
    $siteRoot . '/config/site.json'
);
$files = [
    'application/shared/RuntimeInterface.php',
    'application/shared/Application.php',
    'application/back/api/controllers/BackendApiController.php',
    'application/back/Runtime.php',
];

$fileBoundary = File::instance();
foreach ($files as $relative) {
    $path = $siteRoot . '/' . $relative;
    if (!$fileBoundary->exists($path)) {
        throw new RuntimeException(
            'OWASYS_BACK_COMPONENT_MISSING:' . $relative
        );
    }
    require_once $path;
}

OwasysApplication::instance(
    $siteRoot,
    new OwasysBackRuntime($siteRoot)
)->run();

return true;
