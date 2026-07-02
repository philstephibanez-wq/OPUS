<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$siteRoot = $root . '/sites/opus-manager';
$controllerRoot = $siteRoot . '/src/Controller';

$requiredFiles = [
    $siteRoot . '/public/router.php',
    $siteRoot . '/public/index.php',
    $siteRoot . '/public/opus-manager-ui.css',
    $siteRoot . '/README.md',
    $siteRoot . '/DOC/OPUS_MANAGER_CONTROLLER_SHELL_REUSE.md',
    $root . '/DOC/OPUS_MANAGER_CONTROLLER_SHELL_REUSE.md',
    $root . '/DOC/P7_OPS_FINAL_CLOSURE_SCOPE.md',
];

foreach ($requiredFiles as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPUS_MANAGER_SHELL_FILE_MISSING: ' . $file);
    }
}

$controllers = [
    'OpusManagerDashboardController',
    'CreateSiteController',
    'CreatePackageController',
    'UsersManagerController',
    'AclManagerController',
    'RbacManagerController',
    'SsoManagerController',
    'SessionsManagerController',
    'AuthAuditController',
    'FsmManagerController',
    'ClManagerController',
    'ModelsManagerController',
    'DatabaseManagerController',
    'OdbcManagerController',
    'LstsarManagerController',
    'ComposerManagerController',
    'RefBookController',
    'UserBookController',
    'LogsController',
    'DiagnosticsController',
];

foreach ($controllers as $controller) {
    $file = $controllerRoot . '/' . $controller . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('OPUS_MANAGER_SHELL_CONTROLLER_MISSING: ' . $controller);
    }
}

$combined = '';
foreach (array_merge($requiredFiles, glob($controllerRoot . '/*.php') ?: [], glob($siteRoot . '/src/Service/*.php') ?: []) as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('OPUS_MANAGER_SHELL_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE',
    'Créer un site avec OPUS',
    'CreateSiteController',
    'un controller par fonctionnalité/page',
    'ODBC Manager',
    '/opus-lstsar-manager/odbc-manager',
    'LSTSAR Manager',
    '/opus-lstsar-manager/chain',
    '/opus-lstsar-manager/operations',
    'OpusManagerModuleRegistry',
    'primaryRoute',
    'profiler',
    'OPUS_ENV',
    'prod',
    'Ref Book',
    'User Book',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_SHELL_MARKER_MISSING: ' . $marker);
    }
}

require_once $siteRoot . '/src/Controller/OpusManagerControllerInterface.php';
require_once $siteRoot . '/src/Service/OpusManagerModuleRegistry.php';
require_once $siteRoot . '/src/Controller/AbstractOpusManagerController.php';
foreach ($controllers as $controller) {
    require_once $controllerRoot . '/' . $controller . '.php';
}

$modules = \Opus\Manager\Service\OpusManagerModuleRegistry::modules();
if (count($modules) !== 20) {
    throw new RuntimeException('OPUS_MANAGER_SHELL_MODULE_COUNT_INVALID: ' . count($modules));
}

if (\Opus\Manager\Service\OpusManagerModuleRegistry::primaryRoute() !== '/opus-manager/create-site') {
    throw new RuntimeException('OPUS_MANAGER_SHELL_PRIMARY_ROUTE_INVALID');
}

$routeMap = \Opus\Manager\Service\OpusManagerModuleRegistry::routeMap();
foreach ([
    '/opus-manager/create-site' => 'CreateSiteController',
    '/opus-manager/odbc' => 'OdbcManagerController',
    '/opus-manager/lstsar' => 'LstsarManagerController',
    '/opus-manager/ref-book' => 'RefBookController',
    '/opus-manager/user-book' => 'UserBookController',
] as $route => $controller) {
    if (($routeMap[$route] ?? null) !== $controller) {
        throw new RuntimeException('OPUS_MANAGER_SHELL_ROUTE_MAP_INVALID: ' . $route);
    }
}

$controller = new \Opus\Manager\Controller\CreateSiteController();
$html = $controller->render(['lang' => 'fr', 'env' => 'dev']);
foreach ([
    'Créer un site avec OPUS',
    'StepIdentity',
    'StepOdbc',
    'StepLstsar',
    'StepComposerInstall',
    'OPUS Manager',
] as $marker) {
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_SHELL_RENDER_MARKER_MISSING: ' . $marker);
    }
}

$prodHtml = $controller->render(['lang' => 'fr', 'env' => 'prod']);
if (!str_contains($prodHtml, 'Prod : profiler interdit')) {
    throw new RuntimeException('OPUS_MANAGER_SHELL_PROD_PROFILER_LOCK_MISSING');
}

echo 'CHECK_OPUS_MANAGER_CONTROLLER_SHELL_REUSE=OK' . PHP_EOL;
echo 'OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE_SMOKE_OK' . PHP_EOL;
