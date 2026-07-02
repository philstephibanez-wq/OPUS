<?php
declare(strict_types=1);

$root = getcwd();

$controllerDoc = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE.md';
$scopeDoc = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';
$wizardDoc = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX.md';

$exact = 'Règle canonique : un controller par fonctionnalité/page.';

foreach ([$controllerDoc, $scopeDoc, $wizardDoc] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'OPUS_MANAGER_WIZARD_FIX2_FILE_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

function opus_fix2_read(string $file): string
{
    $source = file_get_contents($file);
    if (!is_string($source)) {
        fwrite(STDERR, 'OPUS_MANAGER_WIZARD_FIX2_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }

    return $source;
}

function opus_fix2_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'OPUS_MANAGER_WIZARD_FIX2_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

$controller = opus_fix2_read($controllerDoc);

if (!str_contains($controller, $exact)) {
    if (str_contains($controller, '## Règle')) {
        $controller = str_replace('## Règle', '## Règle' . PHP_EOL . PHP_EOL . $exact, $controller);
    } else {
        $controller .= PHP_EOL . '## Règle' . PHP_EOL . PHP_EOL . $exact . PHP_EOL;
    }
}

if (!str_contains($controller, 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX2')) {
    $controller .= PHP_EOL;
    $controller .= '## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX2' . PHP_EOL . PHP_EOL;
    $controller .= '- Verrouille la phrase exacte attendue par le smoke : `' . $exact . '`.' . PHP_EOL;
}

opus_fix2_write($controllerDoc, $controller);

$scope = opus_fix2_read($scopeDoc);
if (!str_contains($scope, 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX2')) {
    $scope .= PHP_EOL;
    $scope .= '## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX2' . PHP_EOL . PHP_EOL;
    $scope .= '- Correction du smoke Create Site Wizard : phrase exacte controller ajoutée.' . PHP_EOL;
}
opus_fix2_write($scopeDoc, $scope);

$wizard = opus_fix2_read($wizardDoc);
if (!str_contains($wizard, 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX2')) {
    $wizard .= PHP_EOL;
    $wizard .= '## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX2' . PHP_EOL . PHP_EOL;
    $wizard .= '- Aligné avec `' . $exact . '`.' . PHP_EOL;
}
opus_fix2_write($wizardDoc, $wizard);

$check = opus_fix2_read($controllerDoc);
if (!str_contains($check, $exact)) {
    fwrite(STDERR, 'OPUS_MANAGER_WIZARD_FIX2_EXACT_MARKER_STILL_MISSING' . PHP_EOL);
    exit(1);
}

echo 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX2_OK' . PHP_EOL;
