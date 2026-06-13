<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$composerPath = $projectRoot . '/composer.json';
$composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
$requires = $composer['require'] ?? [];
foreach (array_keys($requires) as $packageName) {
    if (stripos((string) $packageName, 'twig') !== false || stripos((string) $packageName, 'symfony') !== false) {
        fwrite(STDERR, 'P116B2_FORBIDDEN_COMPOSER_DEPENDENCY=' . $packageName . PHP_EOL);
        exit(1);
    }
}

$forbiddenTemplateFiles = [
    'framework/Opus/Template/Adapter.php',
    'framework/Opus/Template/Smarty.php',
    'framework/Opus/Template/Twig.php',
    'framework/Opus/Template/TwigTemplateRenderer.php',
    'framework/Opus/Template/X64.php',
];

foreach ($forbiddenTemplateFiles as $relativePath) {
    if (is_file($projectRoot . '/' . $relativePath)) {
        fwrite(STDERR, 'P116B2_FORBIDDEN_TEMPLATE_ENGINE_FILE_PRESENT=' . $relativePath . PHP_EOL);
        exit(1);
    }
}

$forbiddenRuntimeReferences = [
    'framework/Opus/Application/Application.php' => ['TwigTemplateRenderer', 'Symfony'],
    'framework/Opus/Application/ApplicationPaths.php' => ['/var/cache/twig', 'Symfony'],
    'tools/recipes/recipes/TemplateRecipe.php' => ['TwigTemplateRenderer::class', 'Adapter::class', 'Smarty::class', 'X64::class'],
];

foreach ($forbiddenRuntimeReferences as $relativePath => $needles) {
    $absolutePath = $projectRoot . '/' . $relativePath;
    if (!is_file($absolutePath)) {
        fwrite(STDERR, 'P116B2_RUNTIME_REFERENCE_FILE_MISSING=' . $relativePath . PHP_EOL);
        exit(1);
    }

    $source = (string) file_get_contents($absolutePath);
    foreach ($needles as $needle) {
        if (str_contains($source, $needle)) {
            fwrite(STDERR, 'P116B2_FORBIDDEN_RUNTIME_REFERENCE=' . $relativePath . ' :: ' . $needle . PHP_EOL);
            exit(1);
        }
    }
}

require_once $projectRoot . '/vendor/autoload.php';

use Opus\Documentation\RuntimeClassCatalog;

$root = $projectRoot . '/framework/Opus';
$catalog = new RuntimeClassCatalog($root);
$classes = $catalog->all();

if ($classes === []) {
    fwrite(STDERR, 'P116B2_LIVE_CLASS_CATALOG_EMPTY' . PHP_EOL);
    exit(1);
}

$diagnostics = $catalog->diagnostics();
if ($diagnostics !== []) {
    foreach ($diagnostics as $diagnostic) {
        fwrite(STDERR, $diagnostic . PHP_EOL);
    }
    fwrite(STDERR, 'P116B2_LIVE_CLASS_CATALOG_DIAGNOSTICS_FOUND=' . count($diagnostics) . PHP_EOL);
    exit(1);
}

$foundCatalog = false;
$foundLegacySimpleXml = false;
$foundLegacySingleton = false;

foreach ($classes as $class) {
    $data = $class->toArray();
    if (($data['domain'] ?? '') === 'unclassified') {
        fwrite(STDERR, 'P116B2_UNCLASSIFIED_DOMAIN_FOUND=' . ($data['name'] ?? '?') . PHP_EOL);
        exit(1);
    }
    if (($data['domain'] ?? '') === '' || ($data['domain'] ?? '') === 'unknown') {
        fwrite(STDERR, 'P116B2_INVALID_DOMAIN_FOUND=' . ($data['name'] ?? '?') . PHP_EOL);
        exit(1);
    }
    if (!is_file((string) ($data['file'] ?? ''))) {
        fwrite(STDERR, 'P116B2_REFLECTED_FILE_MISSING=' . ($data['name'] ?? '?') . PHP_EOL);
        exit(1);
    }
    if (($data['name'] ?? '') === RuntimeClassCatalog::class) {
        $foundCatalog = true;
    }
    if (($data['name'] ?? '') === 'OPUS_SimpleXMLElementExtended' && ($data['domain'] ?? '') === 'Compatibility') {
        $foundLegacySimpleXml = true;
    }
    if (($data['name'] ?? '') === 'OPUS_Singleton' && ($data['domain'] ?? '') === 'Compatibility') {
        $foundLegacySingleton = true;
    }
}

if (!$foundCatalog) {
    fwrite(STDERR, 'P116B2_RUNTIME_CLASS_CATALOG_NOT_DISCOVERED' . PHP_EOL);
    exit(1);
}

if (!$foundLegacySimpleXml || !$foundLegacySingleton) {
    fwrite(STDERR, 'P116B2_LEGACY_GLOBAL_COMPATIBILITY_SYMBOLS_NOT_DISCOVERED' . PHP_EOL);
    exit(1);
}

$matches = $catalog->search('RuntimeClassCatalog');
if ($matches === []) {
    fwrite(STDERR, 'P116B2_LIVE_SEARCH_FAILED' . PHP_EOL);
    exit(1);
}

$encodedClasses = json_encode($classes, JSON_THROW_ON_ERROR);
if (stripos($encodedClasses, 'unclassified') !== false) {
    fwrite(STDERR, 'P116B2_FORBIDDEN_UNCLASSIFIED_TEXT_FOUND' . PHP_EOL);
    exit(1);
}

if (stripos($encodedClasses, 'TwigTemplateRenderer') !== false || stripos($encodedClasses, 'Opus\\Template\\Smarty') !== false) {
    fwrite(STDERR, 'P116B2_FORBIDDEN_LEGACY_TEMPLATE_SYMBOL_FOUND' . PHP_EOL);
    exit(1);
}

echo 'P116B2_LIVE_CLASS_CATALOG_SMOKE_OK' . PHP_EOL;
echo 'live_classes=' . count($classes) . PHP_EOL;
echo 'diagnostics=0' . PHP_EOL;
echo 'composer_forbidden_dependencies=0' . PHP_EOL;
echo 'runtime_legacy_template_references=0' . PHP_EOL;
