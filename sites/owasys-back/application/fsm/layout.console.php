<?php
declare(strict_types=1);

use Opus\File\File;

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));
$files = [
    'application/fsm/services/OwasysFsmLayoutCommandProviderInterface.php',
    'application/fsm/services/OwasysFsmLayoutCommandProvider.php',
];
$fileBoundary = File::instance();
foreach ($files as $relative) {
    $path = $siteRoot . '/' . $relative;
    if (!$fileBoundary->exists($path)) {
        throw new RuntimeException(
            'OWASYS_BACK_FSM_LAYOUT_COMPONENT_MISSING:' . $relative
        );
    }
    require_once $path;
}
return new OwasysFsmLayoutCommandProvider($siteRoot, $opusRoot);
