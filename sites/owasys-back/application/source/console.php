<?php
declare(strict_types=1);

use Opus\File\File;

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));
$files = [
    'application/source/services/OwasysSourceCommandProviderInterface.php',
    'application/source/services/OwasysSourceCommandProvider.php',
];
$fileBoundary = File::instance();
foreach ($files as $relative) {
    $path = $siteRoot . '/' . $relative;
    if (!$fileBoundary->exists($path)) {
        throw new RuntimeException(
            'OWASYS_BACK_SOURCE_COMPONENT_MISSING:' . $relative
        );
    }
    require_once $path;
}
return new OwasysSourceCommandProvider($siteRoot, $opusRoot);
