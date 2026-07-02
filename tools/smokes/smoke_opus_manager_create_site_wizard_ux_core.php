<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);

$files = [
    $root . '/DOC/OPUS_MANAGER_CREATE_SITE_WIZARD_UX.md',
    $root . '/DOC/P7_OPS_FINAL_CLOSURE_SCOPE.md',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('CREATE_SITE_WIZARD_FILE_MISSING: ' . $file);
    }
}

$doc = file_get_contents($root . '/DOC/OPUS_MANAGER_CREATE_SITE_WIZARD_UX.md');
if (!is_string($doc)) {
    throw new RuntimeException('CREATE_SITE_WIZARD_DOC_READ_FAILED');
}

$markers = [
    'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE',
    'Créer un site avec OPUS',
    'Expérience utilisateur : assistant clair',
    'CreateSiteController',
    'StepIdentity',
    'StepSiteType',
    'StepTemplate',
    'StepLanguages',
    'StepModules',
    'StepSecurity',
    'StepData',
    'StepOdbc',
    'StepLstsar',
    'StepComposerInstall',
    'StepSmokeTests',
    'StepSummary',
    'ODBC Manager',
    'LSTSAR Manager',
    'composer validate --strict',
    'composer install --no-dev',
    'toutes les langues officielles UE + ukrainien',
    'En prod',
    'aucun profiler',
    'User Book',
    'Ref Book',
];

foreach ($markers as $marker) {
    if (!str_contains($doc, $marker)) {
        throw new RuntimeException('CREATE_SITE_WIZARD_MARKER_MISSING: ' . $marker);
    }
}

$scope = file_get_contents($root . '/DOC/P7_OPS_FINAL_CLOSURE_SCOPE.md');
if (!is_string($scope) || !str_contains($scope, 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE')) {
    throw new RuntimeException('CREATE_SITE_WIZARD_SCOPE_NOT_UPDATED');
}

$controllerDoc = $root . '/DOC/OPUS_MANAGER_CONTROLLER_ARCHITECTURE.md';
if (is_file($controllerDoc)) {
    $controller = file_get_contents($controllerDoc);
    if (!is_string($controller) || !str_contains($controller, 'Règle canonique : un controller par fonctionnalité/page.')) {
        throw new RuntimeException('CREATE_SITE_WIZARD_CONTROLLER_CANONICAL_RULE_MISSING');
    }
}

echo 'CHECK_OPUS_MANAGER_CREATE_SITE_WIZARD_DOC=OK' . PHP_EOL;
echo 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_SMOKE_OK' . PHP_EOL;
