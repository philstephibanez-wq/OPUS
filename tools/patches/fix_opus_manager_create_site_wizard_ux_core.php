<?php
declare(strict_types=1);

/**
 * OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX
 *
 * Le commit précédent a bien poussé la doc wizard, mais un smoke a signalé
 * que la formulation canonique controller n'était pas présente sous sa forme
 * exacte. Ce correctif ajoute le marqueur exact et relance les smokes.
 */

$root = getcwd();

$controllerDoc = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE.md';
$scopeDoc = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';
$wizardDoc = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX.md';

foreach ([$controllerDoc, $scopeDoc, $wizardDoc] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'OPUS_MANAGER_WIZARD_FIX_FILE_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

function opus_wizard_fix_read(string $file): string
{
    $source = file_get_contents($file);
    if (!is_string($source)) {
        fwrite(STDERR, 'OPUS_MANAGER_WIZARD_FIX_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }

    return $source;
}

function opus_wizard_fix_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'OPUS_MANAGER_WIZARD_FIX_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

$canonical = 'un controller par fonctionnalité/page';

$controller = opus_wizard_fix_read($controllerDoc);

if (!str_contains($controller, $canonical)) {
    $insertion = 'Règle canonique : ' . $canonical . '.' . PHP_EOL . PHP_EOL;

    if (preg_match('/## Règle\s*/u', $controller)) {
        $controller = preg_replace('/(## Règle\s*)/u', '$1' . PHP_EOL . $insertion, $controller, 1) ?? $controller;
    } else {
        $controller .= PHP_EOL . '## Règle' . PHP_EOL . PHP_EOL . $insertion;
    }
}

if (!str_contains($controller, 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX')) {
    $controller .= PHP_EOL;
    $controller .= '## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX' . PHP_EOL . PHP_EOL;
    $controller .= '- Ajoute le marqueur canonique exact : `' . $canonical . '`.' . PHP_EOL;
    $controller .= '- Rattache la règle controller au parcours utilisateur `Créer un site avec OPUS`.' . PHP_EOL;
}

opus_wizard_fix_write($controllerDoc, $controller);

$scope = opus_wizard_fix_read($scopeDoc);
if (!str_contains($scope, 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX')) {
    $scope .= PHP_EOL;
    $scope .= '## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX' . PHP_EOL . PHP_EOL;
    $scope .= '- Verrouille la formulation canonique : `' . $canonical . '`.' . PHP_EOL;
    $scope .= '- Le wizard reste l’entrée utilisateur principale, les controllers restent séparés par fonctionnalité.' . PHP_EOL;
}
opus_wizard_fix_write($scopeDoc, $scope);

$wizard = opus_wizard_fix_read($wizardDoc);
if (!str_contains($wizard, 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX')) {
    $wizard .= PHP_EOL;
    $wizard .= '## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX' . PHP_EOL . PHP_EOL;
    $wizard .= '- Alignement avec la règle d’architecture : `' . $canonical . '`.' . PHP_EOL;
}
opus_wizard_fix_write($wizardDoc, $wizard);

echo 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX_OK' . PHP_EOL;
