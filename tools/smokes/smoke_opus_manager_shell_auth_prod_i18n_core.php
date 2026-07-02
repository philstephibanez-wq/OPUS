<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$siteRoot = $root . '/sites/opus-manager';
$files = [
    $siteRoot . '/config/environment.dev.php',
    $siteRoot . '/config/environment.prod.example.php',
    $siteRoot . '/config/environment.php',
    $siteRoot . '/src/Service/OpusManagerEnvironment.php',
    $siteRoot . '/src/Service/OpusManagerI18n.php',
    $siteRoot . '/src/Service/OpusManagerAuth.php',
    $siteRoot . '/src/Controller/SignInController.php',
    $siteRoot . '/src/Controller/LogoutController.php',
    $siteRoot . '/src/Controller/AbstractOpusManagerController.php',
    $siteRoot . '/public/router.php',
    $siteRoot . '/public/opus-manager-ui.css',
    $siteRoot . '/README.md',
    $root . '/DOC/OPUS_MANAGER_SHELL_AUTH_PROD_I18N.md',
    $root . '/DOC/OPUS_DEV_DELIVERY_SCOPE.md',
    $root . '/DOC/P7_OPS_FINAL_CLOSURE_SCOPE.md',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPUS_MANAGER_AUTH_PROD_I18N_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('OPUS_MANAGER_AUTH_PROD_I18N_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE',
    'OPUS Manager fait partie de la livraison dev OPUS',
    'SignInController',
    'LogoutController',
    'OpusManagerAuth',
    'OpusManagerEnvironment',
    'OpusManagerI18n',
    'SESSION_NAME',
    'OPUSMANAGER',
    'environment.prod.example.php',
    'profiler_allowed',
    'profiler=1',
    'aucun profiler/debug',
    'toutes les langues officielles UE + ukrainien',
    'uk',
    'admin / admin',
    'CreateSiteController',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_AUTH_PROD_I18N_MARKER_MISSING: ' . $marker);
    }
}

require_once $siteRoot . '/src/Service/OpusManagerI18n.php';
require_once $siteRoot . '/src/Service/OpusManagerEnvironment.php';
require_once $siteRoot . '/src/Service/OpusManagerAuth.php';
require_once $siteRoot . '/src/Service/OpusManagerModuleRegistry.php';
require_once $siteRoot . '/src/Controller/OpusManagerControllerInterface.php';
require_once $siteRoot . '/src/Controller/AbstractOpusManagerController.php';
require_once $siteRoot . '/src/Controller/SignInController.php';
require_once $siteRoot . '/src/Controller/LogoutController.php';
require_once $siteRoot . '/src/Controller/CreateSiteController.php';

if (count(\Opus\Manager\Service\OpusManagerI18n::SUPPORTED_LANGUAGES) !== 25) {
    throw new RuntimeException('OPUS_MANAGER_I18N_LANGUAGE_COUNT_INVALID');
}

foreach (['bg','hr','cs','da','nl','en','et','fi','fr','de','el','hu','ga','it','lv','lt','mt','pl','pt','ro','sk','sl','es','sv','uk'] as $lang) {
    if (\Opus\Manager\Service\OpusManagerI18n::normalize($lang) !== $lang) {
        throw new RuntimeException('OPUS_MANAGER_I18N_LANGUAGE_MISSING: ' . $lang);
    }
}

$prod = new \Opus\Manager\Service\OpusManagerEnvironment([
    'environment' => 'prod',
    'debug' => true,
    'profiler_allowed' => true,
    'auth_required' => true,
]);

if (!$prod->isProd() || $prod->profilerAllowed() || $prod->debugAllowed()) {
    throw new RuntimeException('OPUS_MANAGER_PROD_LOCK_FAILED');
}

$dev = new \Opus\Manager\Service\OpusManagerEnvironment([
    'environment' => 'dev',
    'debug' => true,
    'profiler_allowed' => true,
    'auth_required' => true,
    'dev_admin_user' => 'admin',
    'dev_admin_password' => 'admin',
]);

if ($dev->isProd() || !$dev->profilerAllowed() || !$dev->debugAllowed()) {
    throw new RuntimeException('OPUS_MANAGER_DEV_ENV_FAILED');
}

$controller = new \Opus\Manager\Controller\CreateSiteController();
$html = $controller->render([
    'lang' => 'uk',
    'env' => 'prod',
    'signed_in' => true,
    'user' => 'admin',
]);

foreach ([
    'Українська',
    'Prod : profiler interdit',
    'Logout',
    'Créer un site avec OPUS',
] as $marker) {
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_AUTH_PROD_I18N_RENDER_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_OPUS_MANAGER_AUTH_PROD_I18N=OK' . PHP_EOL;
echo 'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE_SMOKE_OK' . PHP_EOL;
