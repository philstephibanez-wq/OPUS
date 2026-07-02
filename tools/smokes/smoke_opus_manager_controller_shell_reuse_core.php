<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$siteRoot = $root . '/sites/opus-manager';

spl_autoload_register(static function (string $class) use ($siteRoot): void {
    $prefix = 'Opus\\Manager\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $siteRoot . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$map = \Opus\Manager\Service\OpusManagerModuleRegistry::routeMap();
if (($map['/opus-manager/create-site'] ?? null) !== 'CreateSiteController') {
    throw new RuntimeException('OPUS_MANAGER_CREATE_SITE_ROUTE_MISSING');
}

$html = (new \Opus\Manager\Controller\CreateSiteController())->render([
    'lang' => 'fr',
    'env' => 'dev',
    'signed_in' => true,
    'user' => 'admin',
]);

foreach ([
    'OPUS Manager',
    'Créer un site avec OPUS',
    'StepTechnicalArchitecture',
    'Fullstack',
    'Frontend',
    'Backend',
] as $marker) {
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_CONTROLLER_SHELL_REUSE_MARKER_MISSING: ' . $marker);
    }
}

echo 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE_SMOKE_OK' . PHP_EOL;