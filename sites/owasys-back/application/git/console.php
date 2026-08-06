<?php
declare(strict_types=1);

use Opus\File\File;

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));
$files = [
    'application/git/services/OwasysGitCommandProviderInterface.php',
    'application/git/services/OwasysGitCommandProvider.php',
];
$fileBoundary = File::instance();
foreach ($files as $relative) {
    $path = $siteRoot . '/' . $relative;
    if (!$fileBoundary->exists($path)) {
        throw new RuntimeException(
            'OWASYS_BACK_GIT_COMPONENT_MISSING:' . $relative
        );
    }
    require_once $path;
}

return new OwasysGitCommandProvider($siteRoot, $opusRoot);
