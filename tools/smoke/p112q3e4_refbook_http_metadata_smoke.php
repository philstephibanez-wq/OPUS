<?php

declare(strict_types=1);

/**
 * P112Q3E4 HTTP RefBook metadata smoke.
 *
 * Public smoke test.
 */
$root = dirname(__DIR__, 2);
$required = [
    'framework/Opus/Http/Request.php',
    'framework/Opus/Http/Response.php',
    'tests/Contract/RefBookHttpMetadataContractTest.php',
    'tools/refbook/p112q3e4_refbook_http_metadata_audit.php',
    'tools/refbook/run_p112q3e4_refbook_http_metadata_strict.cmd',
    'tools/recipes/run_p112q3e4_delivery_recipe.cmd',
];
foreach ($required as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        fwrite(STDERR, 'P112Q3E4_SMOKE_FAILED: FILE_MISSING: ' . $relative . PHP_EOL);
        exit(1);
    }
}

$request = file_get_contents($root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Request.php');
if (!is_string($request) || !str_contains($request, '#[OpusRefBookClass(') || !str_contains($request, 'RefBookInspectableInterface') || !str_contains($request, 'P112Q3E4')) {
    fwrite(STDERR, 'P112Q3E4_SMOKE_FAILED: REQUEST_METADATA_MARKER_MISSING' . PHP_EOL);
    exit(1);
}

$response = file_get_contents($root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Response.php');
if (!is_string($response) || !str_contains($response, '#[OpusRefBookClass(') || !str_contains($response, 'P112Q3E4') || !str_contains($response, 'OPUS_RESPONSE_STATUS_INVALID')) {
    fwrite(STDERR, 'P112Q3E4_SMOKE_FAILED: RESPONSE_METADATA_MARKER_MISSING' . PHP_EOL);
    exit(1);
}

$audit = file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'refbook' . DIRECTORY_SEPARATOR . 'p112q3e4_refbook_http_metadata_audit.php');
if (!is_string($audit) || !str_contains($audit, 'P112Q3E4_REFBOOK_HTTP_METADATA_AUDIT_OK') || !str_contains($audit, 'snapshot.http.latest.json')) {
    fwrite(STDERR, 'P112Q3E4_SMOKE_FAILED: AUDIT_MARKER_MISSING' . PHP_EOL);
    exit(1);
}

$recipe = file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'recipes' . DIRECTORY_SEPARATOR . 'opus_global_regression_recipe.php');
if (!is_string($recipe) || !str_contains($recipe, 'P112Q3E4_UNIT') || !str_contains($recipe, 'P112Q3E4_SMOKE')) {
    fwrite(STDERR, 'P112Q3E4_SMOKE_FAILED: GLOBAL_RECIPE_STEP_MISSING' . PHP_EOL);
    exit(1);
}

echo 'P112Q3E4_REFBOOK_HTTP_METADATA_SMOKE_OK' . PHP_EOL;
exit(0);
