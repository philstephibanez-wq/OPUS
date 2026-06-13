<?php

declare(strict_types=1);

/**
 * P112Q3E3 ROUTING RefBook metadata smoke.
 *
 * Public smoke test.
 */
$root = dirname(__DIR__, 2);
$required = [
    'framework/Opus/Routing/AttributeRouteProvider.php',
    'framework/Opus/Routing/ClassIndex.php',
    'framework/Opus/Routing/Route.php',
    'framework/Opus/Routing/RouteCompilerException.php',
    'framework/Opus/Routing/RouteDefinition.php',
    'framework/Opus/Routing/RouteManifestCompiler.php',
    'framework/Opus/Routing/RouteMatch.php',
    'framework/Opus/Routing/Router.php',
    'tests/Contract/RefBookRoutingMetadataContractTest.php',
    'tools/refbook/p112q3e3_refbook_routing_metadata_audit.php',
    'tools/refbook/run_p112q3e3_refbook_routing_metadata_strict.cmd',
    'tools/recipes/run_p112q3e3_delivery_recipe.cmd',
];
foreach ($required as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        fwrite(STDERR, 'P112Q3E3_SMOKE_FAILED: FILE_MISSING: ' . $relative . PHP_EOL);
        exit(1);
    }
}

$router = file_get_contents($root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Routing' . DIRECTORY_SEPARATOR . 'Router.php');
if (!is_string($router) || !str_contains($router, '#[OpusRefBookClass(') || !str_contains($router, 'RefBookInspectableInterface') || !str_contains($router, 'P112Q3E3')) {
    fwrite(STDERR, 'P112Q3E3_SMOKE_FAILED: ROUTER_METADATA_MARKER_MISSING' . PHP_EOL);
    exit(1);
}

$routeDefinition = file_get_contents($root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Routing' . DIRECTORY_SEPARATOR . 'RouteDefinition.php');
if (!is_string($routeDefinition) || !str_contains($routeDefinition, '#[OpusRefBookMethod(') || !str_contains($routeDefinition, 'toManifestRow')) {
    fwrite(STDERR, 'P112Q3E3_SMOKE_FAILED: ROUTE_DEFINITION_METADATA_MARKER_MISSING' . PHP_EOL);
    exit(1);
}


$attributeRouteProvider = file_get_contents($root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Routing' . DIRECTORY_SEPARATOR . 'AttributeRouteProvider.php');
if (!is_string($attributeRouteProvider) || !str_contains($attributeRouteProvider, '#[OpusRefBookClass(') || !str_contains($attributeRouteProvider, 'RefBookInspectableInterface') || !str_contains($attributeRouteProvider, 'P112Q3E3')) {
    fwrite(STDERR, 'P112Q3E3_SMOKE_FAILED: ATTRIBUTE_ROUTE_PROVIDER_METADATA_MARKER_MISSING' . PHP_EOL);
    exit(1);
}

$routeManifestCompiler = file_get_contents($root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Routing' . DIRECTORY_SEPARATOR . 'RouteManifestCompiler.php');
if (!is_string($routeManifestCompiler) || !str_contains($routeManifestCompiler, '#[OpusRefBookClass(') || !str_contains($routeManifestCompiler, 'RefBookInspectableInterface') || !str_contains($routeManifestCompiler, 'writePhpManifest')) {
    fwrite(STDERR, 'P112Q3E3_SMOKE_FAILED: ROUTE_MANIFEST_COMPILER_METADATA_MARKER_MISSING' . PHP_EOL);
    exit(1);
}

$audit = file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'refbook' . DIRECTORY_SEPARATOR . 'p112q3e3_refbook_routing_metadata_audit.php');
if (!is_string($audit) || !str_contains($audit, 'P112Q3E3_REFBOOK_ROUTING_METADATA_AUDIT_OK') || !str_contains($audit, 'snapshot.routing.latest.json')) {
    fwrite(STDERR, 'P112Q3E3_SMOKE_FAILED: AUDIT_MARKER_MISSING' . PHP_EOL);
    exit(1);
}

$recipe = file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'recipes' . DIRECTORY_SEPARATOR . 'opus_global_regression_recipe.php');
if (!is_string($recipe) || !str_contains($recipe, 'P112Q3E3_UNIT') || !str_contains($recipe, 'P112Q3E3_SMOKE')) {
    fwrite(STDERR, 'P112Q3E3_SMOKE_FAILED: GLOBAL_RECIPE_STEP_MISSING' . PHP_EOL);
    exit(1);
}

echo 'P112Q3E3_REFBOOK_ROUTING_METADATA_SMOKE_OK' . PHP_EOL;
exit(0);
