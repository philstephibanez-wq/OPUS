<?php
declare(strict_types=1);

use Opus\File\File;
use Opus\File\StructuredFileLoader;

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('OWASYS_BACK_COMPOSER_AUTOLOAD_MISSING');
}
require_once $autoload;
$files = [
    'application/api/controllers/BackendApiController.php',
    'application/default/ApplicationInterface.php',
    'application/default/Application.php',
];
$fileBoundary = File::instance();
foreach ($files as $relative) {
    $path = $siteRoot . '/' . $relative;
    if (!$fileBoundary->exists($path)) {
        throw new RuntimeException('OWASYS_BACK_COMPONENT_MISSING:' . $relative);
    }
    require_once $path;
}
OwasysBackApplication::instance($siteRoot)->run();
return true;
