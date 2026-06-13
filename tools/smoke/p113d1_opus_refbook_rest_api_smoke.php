<?php

declare(strict_types=1);

/**
 * P113D1C Opus RefBook REST API smoke.
 *
 * Public smoke test.
 * Contract:
 *   - the REST API classes and public endpoint exist;
 *   - examples and diagrams are official DOC/refbook assets;
 *   - the endpoint catalog is owned by the snapshot provider;
 *   - the REST dispatcher may route normalized relative paths internally;
 *   - the global regression recipe includes the P113D1 unit and smoke gates.
 */
$root = dirname(__DIR__, 2);

$required = [
    'framework/Opus/RefBook/Api/RefBookDocumentationAssetRepository.php',
    'framework/Opus/RefBook/Api/RefBookRestSnapshotProvider.php',
    'framework/Opus/RefBook/Api/RefBookRestApi.php',
    'public/api/refbook.php',
    'tools/server/opus_refbook_rest_router.php',
    'tests/Contract/RefBookRestApiContractTest.php',
    'tools/recipes/p113d1_opus_refbook_rest_api_robotized_recipe.php',
    'tools/recipes/run_p113d1_opus_refbook_rest_api_robotized_recipe.cmd',
    'DOC/refbook/examples/refbook-rest-api-client.php',
    'DOC/refbook/examples/fsm-state-machine-runtime.php',
    'DOC/refbook/diagrams/framework-fsm-runtime.mmd',
    'DOC/refbook/diagrams/refbook-rest-api-flow.mmd',
    'DOC/patches/P113D1_OPUS_REFBOOK_REST_API/PATCH.md',
    'DOC/patches/P113D1_OPUS_REFBOOK_REST_API/CHANGELOG.md',
];

foreach ($required as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        fwrite(STDERR, 'P113D1_SMOKE_FAILED: FILE_MISSING: ' . $relative . PHP_EOL);
        exit(1);
    }
}

$globalRecipe = readText($root, 'tools/recipes/opus_global_regression_recipe.php');
if (!str_contains($globalRecipe, 'P113D1_UNIT') || !str_contains($globalRecipe, 'RefBookRestApiContractTest.php') || !str_contains($globalRecipe, 'P113D1_SMOKE')) {
    fwrite(STDERR, 'P113D1_SMOKE_FAILED: GLOBAL_RECIPE_STEP_MISSING' . PHP_EOL);
    exit(1);
}

$provider = readText($root, 'framework/Opus/RefBook/Api/RefBookRestSnapshotProvider.php');
foreach (['/api/refbook/snapshot', '/api/refbook/domains', '/api/refbook/classes', '/api/refbook/examples/{id}', '/api/refbook/diagrams/{id}'] as $needle) {
    if (!str_contains($provider, $needle)) {
        fwrite(STDERR, 'P113D1_SMOKE_FAILED: REST_ENDPOINT_CATALOG_MISSING: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$api = readText($root, 'framework/Opus/RefBook/Api/RefBookRestApi.php');
foreach (['/snapshot', '/domains', '/classes', '/examples/', '/diagrams/'] as $needle) {
    if (!str_contains($api, $needle)) {
        fwrite(STDERR, 'P113D1_SMOKE_FAILED: REST_DISPATCH_ROUTE_MISSING: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$diagram = readText($root, 'DOC/refbook/diagrams/framework-fsm-runtime.mmd');
if (!str_contains($diagram, 'stateDiagram-v2') || !str_contains($diagram, 'REQUEST_RECEIVED') || !str_contains($diagram, 'RESPONSE_SENT')) {
    fwrite(STDERR, 'P113D1_SMOKE_FAILED: FSM_DIAGRAM_CONTRACT_INVALID' . PHP_EOL);
    exit(1);
}

$example = readText($root, 'DOC/refbook/examples/fsm-state-machine-runtime.php');
if (!str_contains($example, 'TransitionDefinition') || !str_contains($example, 'StateDefinition')) {
    fwrite(STDERR, 'P113D1_SMOKE_FAILED: FSM_CODE_EXAMPLE_CONTRACT_INVALID' . PHP_EOL);
    exit(1);
}

echo 'P113D1_OPUS_REFBOOK_REST_API_SMOKE_OK' . PHP_EOL;
exit(0);

function readText(string $root, string $relative): string
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fwrite(STDERR, 'P113D1_SMOKE_FAILED: FILE_READ_FAILED: ' . $relative . PHP_EOL);
        exit(1);
    }

    return $content;
}
