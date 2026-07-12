<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';

$siteFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
$routesFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.json';

$site = json_decode((string) file_get_contents($siteFile), true);
$routes = json_decode((string) file_get_contents($routesFile), true);

if (!is_array($site)) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_SITE_JSON_INVALID\n");
    exit(1);
}

if (!is_array($routes)) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_ROUTES_JSON_INVALID\n");
    exit(1);
}

$roots = $site['application_roots'] ?? [];
if (!is_array($roots) || !in_array('registry', $roots, true)) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_ROOT_MISSING\n");
    exit(1);
}

if (in_array('applications', $roots, true)) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_AMBIGUOUS_ROOT_PRESENT\n");
    exit(1);
}

$applicationApplications = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'applications';
if (file_exists($applicationApplications)) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_AMBIGUOUS_DIRECTORY_PRESENT\n");
    exit(1);
}

$registryView = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'registry' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.php';
if (!is_file($registryView)) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_REGISTRY_VIEW_MISSING\n");
    exit(1);
}

$matched = false;
foreach ((array) ($routes['routes'] ?? []) as $route) {
    if (!is_array($route) || ($route['path'] ?? null) !== '/applications') {
        continue;
    }

    $matched = true;
    if (($route['controller'] ?? null) !== 'registry') {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_ROUTE_CONTROLLER_INVALID\n");
        exit(1);
    }
    if (($route['view'] ?? null) !== 'application/registry/views/index.php') {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_ROUTE_VIEW_INVALID\n");
        exit(1);
    }
    if (($route['label'] ?? null) !== 'menu.applications') {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_ROUTE_LABEL_INVALID\n");
        exit(1);
    }
}

if (!$matched) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_APPLICATIONS_ROUTE_MISSING\n");
    exit(1);
}

echo "OWASYS_REGISTRY_NAMING_SMOKE_OK\n";
