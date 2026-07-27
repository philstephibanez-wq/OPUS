<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));
$files = [
    'application/registry/services/ApplicationSingletonInspector.php',
    'application/registry/repositories/RegistryRepository.php',
    'application/registry/services/OwasysCommandProviderInterface.php',
    'application/registry/services/OwasysCommandProvider.php',
];

foreach ($files as $relative) {
    $file = $siteRoot . '/' . $relative;
    if (!is_file($file)) {
        throw new RuntimeException(
            'OWASYS_COMPOSER_COMPONENT_MISSING:' . $relative
        );
    }
    require_once $file;
}

return new OwasysCommandProvider($siteRoot, $opusRoot);
