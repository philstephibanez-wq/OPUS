<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$siteRoot = $root . '/sites/opus-manager';

$files = [
    $siteRoot . '/src/Controller/AbstractOpusManagerController.php',
    $siteRoot . '/src/Controller/SignInController.php',
    $siteRoot . '/public/opus-manager-ui.css',
    $root . '/DOC/OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP.md',
    $root . '/DOC/P7_OPS_FINAL_CLOSURE_SCOPE.md',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE',
    'Le sélecteur de langue suffit',
    'om-auth-badges:empty',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_MARKER_MISSING: ' . $marker);
    }
}

require_once $siteRoot . '/src/Service/OpusManagerI18n.php';
require_once $siteRoot . '/src/Service/OpusManagerEnvironment.php';
require_once $siteRoot . '/src/Service/OpusManagerAuth.php';
require_once $siteRoot . '/src/Service/OpusManagerModuleRegistry.php';
require_once $siteRoot . '/src/Controller/OpusManagerControllerInterface.php';
require_once $siteRoot . '/src/Controller/AbstractOpusManagerController.php';
require_once $siteRoot . '/src/Controller/SignInController.php';
require_once $siteRoot . '/src/Controller/CreateSiteController.php';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['lang' => 'hr'];

$signinHtml = (new \Opus\Manager\Controller\SignInController())->render(['lang' => 'hr']);
if (str_contains($signinHtml, 'Langue :')) {
    throw new RuntimeException('OPUS_MANAGER_SIGNIN_LANGUAGE_DUPLICATE_STILL_VISIBLE');
}
foreach ([
    '<select name="lang"',
    'Hrvatski',
    'Dev : admin / admin',
] as $marker) {
    if (!str_contains($signinHtml, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_SIGNIN_LANGUAGE_SELECTOR_MARKER_MISSING: ' . $marker);
    }
}

$siteHtml = (new \Opus\Manager\Controller\CreateSiteController())->render([
    'lang' => 'hr',
    'env' => 'dev',
    'signed_in' => true,
    'user' => 'admin',
]);
if (str_contains($siteHtml, 'Langue :')) {
    throw new RuntimeException('OPUS_MANAGER_SHELL_LANGUAGE_DUPLICATE_STILL_VISIBLE');
}
foreach ([
    '<select name="lang"',
    'Hrvatski',
    'Créer un site avec OPUS',
] as $marker) {
    if (!str_contains($siteHtml, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_SHELL_LANGUAGE_SELECTOR_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP=OK' . PHP_EOL;
echo 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE_SMOKE_OK' . PHP_EOL;
