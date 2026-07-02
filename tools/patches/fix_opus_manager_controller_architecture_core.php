<?php
declare(strict_types=1);

/**
 * OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_FIX
 *
 * Corrige le marqueur canonique attendu par le smoke précédent.
 */

$root = getcwd();
$doc = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE.md';
$scope = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';

foreach ([$doc] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'OPUS_MANAGER_CONTROLLER_FIX_FILE_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

$source = file_get_contents($doc);
if (!is_string($source)) {
    fwrite(STDERR, 'OPUS_MANAGER_CONTROLLER_FIX_READ_FAILED: ' . $doc . PHP_EOL);
    exit(1);
}

$canonical = 'Règle canonique : un controller par fonctionnalité/page.';
if (!str_contains($source, $canonical)) {
    $source = str_replace(
        "## Règle\n\n",
        "## Règle\n\n" . $canonical . "\n\n",
        $source
    );
}

if (!str_contains($source, 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_FIX')) {
    $source .= PHP_EOL;
    $source .= '## OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_FIX' . PHP_EOL . PHP_EOL;
    $source .= '- Ajoute la formulation canonique smokeable : `un controller par fonctionnalité/page`.' . PHP_EOL;
}

if (file_put_contents($doc, $source) === false) {
    fwrite(STDERR, 'OPUS_MANAGER_CONTROLLER_FIX_WRITE_FAILED: ' . $doc . PHP_EOL);
    exit(1);
}

if (is_file($scope)) {
    $scopeSource = file_get_contents($scope);
    if (is_string($scopeSource) && !str_contains($scopeSource, 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_FIX')) {
        $scopeSource .= PHP_EOL;
        $scopeSource .= '## OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_FIX' . PHP_EOL . PHP_EOL;
        $scopeSource .= '- La règle canonique OPUS Manager est : `un controller par fonctionnalité/page`.' . PHP_EOL;
        file_put_contents($scope, $scopeSource);
    }
}

echo 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_FIX_OK' . PHP_EOL;
