<?php

declare(strict_types=1);

/**
 * P112Q3F Opus global robot + Chrome extension smoke.
 *
 * Public smoke test.
 * Contract:
 *   - the Opus global regression recipe includes this smoke;
 *   - the robotized recipe exists and remains CLI-only;
 *   - the Chrome extension is a local developer helper only;
 *   - no broad host permission or hidden network behavior is allowed.
 */
$root = dirname(__DIR__, 2);

$required = [
    'tools/recipes/opus_global_regression_recipe.php',
    'tools/recipes/p112q3f_opus_global_robotized_recipe.php',
    'tools/recipes/run_p112q3f_opus_global_robotized_recipe.cmd',
    'tools/smoke/p112q3f_opus_global_robot_chrome_extension_smoke.php',
    'tools/smoke/run_p112q3f_opus_global_robot_chrome_extension_smoke.cmd',
    'tools/chrome_extension/opus_runtime_robot/manifest.json',
    'tools/chrome_extension/opus_runtime_robot/content.js',
    'tools/chrome_extension/opus_runtime_robot/popup.html',
    'tools/chrome_extension/opus_runtime_robot/popup.css',
    'tools/chrome_extension/opus_runtime_robot/popup.js',
    'tools/chrome_extension/opus_runtime_robot/README.md',
    'DOC/patches/P112Q3F_OPUS_GLOBAL_ROBOTIZED_RECIPE_CHROME_EXTENSION/PATCH.md',
    'DOC/patches/P112Q3F_OPUS_GLOBAL_ROBOTIZED_RECIPE_CHROME_EXTENSION/CHANGELOG.md',
];

foreach ($required as $relative) {
    $path = pathFromRelative($root, $relative);
    if (!is_file($path)) {
        fwrite(STDERR, 'P112Q3F_SMOKE_FAILED: FILE_MISSING: ' . $relative . PHP_EOL);
        exit(1);
    }
}

$globalRecipe = readFileOrFail($root, 'tools/recipes/opus_global_regression_recipe.php');
if (!str_contains($globalRecipe, 'P112Q3F_SMOKE') || !str_contains($globalRecipe, 'p112q3f_opus_global_robot_chrome_extension_smoke.php')) {
    fwrite(STDERR, 'P112Q3F_SMOKE_FAILED: GLOBAL_RECIPE_STEP_MISSING' . PHP_EOL);
    exit(1);
}

$robotRecipe = readFileOrFail($root, 'tools/recipes/p112q3f_opus_global_robotized_recipe.php');
if (!str_contains($robotRecipe, 'P112Q3F_OPUS_GLOBAL_ROBOTIZED_RECIPE_OK') || !str_contains($robotRecipe, 'opus_global_regression_recipe.php')) {
    fwrite(STDERR, 'P112Q3F_SMOKE_FAILED: ROBOTIZED_RECIPE_CONTRACT_MISSING' . PHP_EOL);
    exit(1);
}

$manifestPath = pathFromRelative($root, 'tools/chrome_extension/opus_runtime_robot/manifest.json');
$manifestJson = file_get_contents($manifestPath);
if (!is_string($manifestJson)) {
    fwrite(STDERR, 'P112Q3F_SMOKE_FAILED: MANIFEST_READ_FAILED' . PHP_EOL);
    exit(1);
}
$manifest = json_decode($manifestJson, true);
if (!is_array($manifest) || ($manifest['manifest_version'] ?? null) !== 3) {
    fwrite(STDERR, 'P112Q3F_SMOKE_FAILED: MANIFEST_V3_REQUIRED' . PHP_EOL);
    exit(1);
}

$serializedManifest = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($serializedManifest) || str_contains($serializedManifest, '<all_urls>') || str_contains($serializedManifest, 'https://*/*')) {
    fwrite(STDERR, 'P112Q3F_SMOKE_FAILED: BROAD_HOST_PERMISSION_FORBIDDEN' . PHP_EOL);
    exit(1);
}

$hostPermissions = $manifest['host_permissions'] ?? [];
if (!is_array($hostPermissions) || !in_array('http://127.0.0.1/*', $hostPermissions, true) || !in_array('http://localhost/*', $hostPermissions, true)) {
    fwrite(STDERR, 'P112Q3F_SMOKE_FAILED: LOCAL_HOST_PERMISSIONS_REQUIRED' . PHP_EOL);
    exit(1);
}

$content = readFileOrFail($root, 'tools/chrome_extension/opus_runtime_robot/content.js');
if (!str_contains($content, 'OPUS_RUNTIME_ROBOT_INSPECT') || str_contains($content, 'fetch(') || str_contains($content, 'XMLHttpRequest')) {
    fwrite(STDERR, 'P112Q3F_SMOKE_FAILED: CONTENT_SCRIPT_CONTRACT_INVALID' . PHP_EOL);
    exit(1);
}

$popup = readFileOrFail($root, 'tools/chrome_extension/opus_runtime_robot/popup.js');
if (!str_contains($popup, 'OPUS_RUNTIME_ROBOT_INSPECT') || !str_contains($popup, 'chrome.tabs.query')) {
    fwrite(STDERR, 'P112Q3F_SMOKE_FAILED: POPUP_CONTRACT_INVALID' . PHP_EOL);
    exit(1);
}

echo 'P112Q3F_OPUS_GLOBAL_ROBOT_CHROME_EXTENSION_SMOKE_OK' . PHP_EOL;
exit(0);

function pathFromRelative(string $root, string $relative): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function readFileOrFail(string $root, string $relative): string
{
    $path = pathFromRelative($root, $relative);
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fwrite(STDERR, 'P112Q3F_SMOKE_FAILED: FILE_READ_FAILED: ' . $relative . PHP_EOL);
        exit(1);
    }
    return $content;
}
