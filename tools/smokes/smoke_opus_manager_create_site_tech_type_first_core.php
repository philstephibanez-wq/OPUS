<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$siteRoot = $root . '/sites/opus-manager';

$files = [
    $siteRoot . '/src/Controller/CreateSiteController.php',
    $root . '/DOC/OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST.md',
    $root . '/DOC/OPUS_MANAGER_CREATE_SITE_WIZARD_UX.md',
    $root . '/DOC/P7_OPS_FINAL_CLOSURE_SCOPE.md',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE',
    'StepTechnicalArchitecture',
    'Fullstack',
    'Frontend',
    'Backend',
    'StepFunctionalSpace',
    'StepApiContract',
    'StepBackendBinding',
    'StepComposerPlan',
    'Frontend ne signifie pas frontoffice',
    'backend ne signifie pas backoffice',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_MARKER_MISSING: ' . $marker);
    }
}

$controllerSource = file_get_contents($siteRoot . '/src/Controller/CreateSiteController.php');
if (!is_string($controllerSource)) {
    throw new RuntimeException('OPUS_MANAGER_CREATE_SITE_CONTROLLER_READ_FAILED');
}

$first = strpos($controllerSource, 'StepTechnicalArchitecture');
$identity = strpos($controllerSource, 'StepIdentity');
$functional = strpos($controllerSource, 'StepFunctionalSpace');

if ($first === false || $identity === false || $functional === false) {
    throw new RuntimeException('OPUS_MANAGER_CREATE_SITE_STEP_ORDER_MARKER_MISSING');
}
if (!($first < $functional && $functional < $identity)) {
    throw new RuntimeException('OPUS_MANAGER_CREATE_SITE_STEP_ORDER_INVALID');
}

require_once $siteRoot . '/src/Service/OpusManagerI18n.php';
require_once $siteRoot . '/src/Service/OpusManagerEnvironment.php';
require_once $siteRoot . '/src/Service/OpusManagerAuth.php';
require_once $siteRoot . '/src/Service/OpusManagerModuleRegistry.php';
require_once $siteRoot . '/src/Controller/OpusManagerControllerInterface.php';
require_once $siteRoot . '/src/Controller/AbstractOpusManagerController.php';
require_once $siteRoot . '/src/Controller/CreateSiteController.php';

$html = (new \Opus\Manager\Controller\CreateSiteController())->render([
    'lang' => 'fr',
    'env' => 'dev',
    'signed_in' => true,
    'user' => 'admin',
]);

foreach ([
    '1. Architecture technique',
    'Fullstack',
    'Frontend',
    'Backend',
    '2. Espace fonctionnel',
    'StepTechnicalArchitecture',
    'StepFunctionalSpace',
    'StepBackendBinding',
] as $marker) {
    if (!str_contains($html, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_RENDER_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST=OK' . PHP_EOL;
echo 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE_SMOKE_OK' . PHP_EOL;
