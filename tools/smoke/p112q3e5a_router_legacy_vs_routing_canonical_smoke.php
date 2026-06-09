<?php

declare(strict_types=1);

/**
 * P112Q3E5A Router legacy vs Routing canonical smoke.
 *
 * Public smoke test.
 *
 * Contract:
 *   - ASAP\Routing remains the canonical runtime routing domain;
 *   - ASAP\Router remains documented as a legacy/public lightweight registry;
 *   - application runtime classes must not depend on ASAP\Router.
 */
$root = dirname(__DIR__, 2);

$required = [
    'framework/Asap/Router/Route.php',
    'framework/Asap/Router/Router.php',
    'framework/Asap/Router/README.md',
    'framework/Asap/Routing/Router.php',
    'framework/Asap/Routing/RouteDefinition.php',
    'framework/Asap/Routing/RouteMatch.php',
    'framework/Asap/Routing/README.md',
    'framework/Asap/Application/Application.php',
    'framework/Asap/Security/SecureDispatchGate.php',
    'framework/Asap/Controller/ControllerDispatcher.php',
];

foreach ($required as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        fwrite(STDERR, 'P112Q3E5A_SMOKE_FAILED: FILE_MISSING: ' . $relative . PHP_EOL);
        exit(1);
    }
}

$routingReadme = readFileOrFail($root, 'framework/Asap/Routing/README.md');
if (
    !str_contains($routingReadme, 'canonical runtime routing domain')
    || !str_contains($routingReadme, 'ASAP\\Routing\\Router')
    || !str_contains($routingReadme, 'ASAP\\Routing\\RouteMatch')
    || !str_contains($routingReadme, 'Application')
) {
    fwrite(STDERR, 'P112Q3E5A_SMOKE_FAILED: ROUTING_README_CONTRACT_MISSING' . PHP_EOL);
    exit(1);
}

$routerReadme = readFileOrFail($root, 'framework/Asap/Router/README.md');
if (
    !str_contains($routerReadme, 'legacy/public lightweight route registry')
    || !str_contains($routerReadme, 'not the canonical application runtime router')
    || !str_contains($routerReadme, 'Do not delete this domain without a dedicated cleanup palier')
) {
    fwrite(STDERR, 'P112Q3E5A_SMOKE_FAILED: ROUTER_README_CONTRACT_MISSING' . PHP_EOL);
    exit(1);
}

$runtimeFiles = [
    'framework/Asap/Application/Application.php',
    'framework/Asap/Security/SecureDispatchGate.php',
    'framework/Asap/Controller/ControllerDispatcher.php',
];

foreach ($runtimeFiles as $relative) {
    $content = readFileOrFail($root, $relative);

    if (str_contains($content, 'use ASAP\\Router\\')) {
        fwrite(STDERR, 'P112Q3E5A_SMOKE_FAILED: LEGACY_ROUTER_IMPORT_IN_RUNTIME: ' . $relative . PHP_EOL);
        exit(1);
    }
}

$application = readFileOrFail($root, 'framework/Asap/Application/Application.php');
if (!str_contains($application, 'use ASAP\\Routing\\Router;')) {
    fwrite(STDERR, 'P112Q3E5A_SMOKE_FAILED: APPLICATION_CANONICAL_ROUTING_IMPORT_MISSING' . PHP_EOL);
    exit(1);
}

$security = readFileOrFail($root, 'framework/Asap/Security/SecureDispatchGate.php');
if (!str_contains($security, 'use ASAP\\Routing\\RouteMatch;')) {
    fwrite(STDERR, 'P112Q3E5A_SMOKE_FAILED: SECURITY_ROUTEMATCH_IMPORT_MISSING' . PHP_EOL);
    exit(1);
}

$dispatcher = readFileOrFail($root, 'framework/Asap/Controller/ControllerDispatcher.php');
if (!str_contains($dispatcher, 'use ASAP\\Routing\\RouteMatch;')) {
    fwrite(STDERR, 'P112Q3E5A_SMOKE_FAILED: CONTROLLER_ROUTEMATCH_IMPORT_MISSING' . PHP_EOL);
    exit(1);
}

echo 'P112Q3E5A_ROUTER_LEGACY_VS_ROUTING_CANONICAL_SMOKE_OK' . PHP_EOL;
exit(0);

function readFileOrFail(string $root, string $relative): string
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $content = file_get_contents($path);

    if (!is_string($content)) {
        fwrite(STDERR, 'P112Q3E5A_SMOKE_FAILED: FILE_READ_FAILED: ' . $relative . PHP_EOL);
        exit(1);
    }

    return $content;
}
