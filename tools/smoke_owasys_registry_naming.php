<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';

$siteFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
$routesFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.json';
$seedFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'registry.seed.json';
$registryView = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'registry' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.php';
$frontFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';
$cssFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'owasys.css';
$jsFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'owasys.js';

foreach ([$siteFile, $routesFile, $seedFile, $registryView, $frontFile, $cssFile, $jsFile] as $requiredFile) {
    if (!is_file($requiredFile)) {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_REQUIRED_FILE_MISSING: {$requiredFile}\n");
        exit(1);
    }
}

foreach ([$registryView, $frontFile] as $lintFile) {
    $lintCommand = PHP_BINARY . ' -l ' . escapeshellarg($lintFile) . ' 2>&1';
    $lintOutput = [];
    $lintCode = 0;
    exec($lintCommand, $lintOutput, $lintCode);
    if ($lintCode !== 0) {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_PARSE_ERROR: {$lintFile}\n" . implode("\n", $lintOutput) . "\n");
        exit(1);
    }
}

$site = json_decode((string) file_get_contents($siteFile), true);
$routes = json_decode((string) file_get_contents($routesFile), true);
$seed = json_decode((string) file_get_contents($seedFile), true);

if (!is_array($site)) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_SITE_JSON_INVALID\n");
    exit(1);
}

if (!is_array($routes)) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_ROUTES_JSON_INVALID\n");
    exit(1);
}

if (!is_array($seed) || ($seed['contract'] ?? null) !== 'OWASYS_REGISTRY_SEED_V1') {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_SEED_INVALID\n");
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

$seedDemo = null;
foreach ((array) ($seed['applications'] ?? []) as $application) {
    if (is_array($application) && ($application['id'] ?? null) === 'demo-app') {
        $seedDemo = $application;
        break;
    }
}
if (!is_array($seedDemo) || ($seedDemo['kind'] ?? null) !== 'fullstack') {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_DEMO_APP_SEED_MISSING\n");
    exit(1);
}

$page = require $registryView;
if (!is_array($page)) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_VIEW_MODEL_INVALID\n");
    exit(1);
}

$entries = is_array($page['registry_entries'] ?? null) ? $page['registry_entries'] : [];
$demo = null;
foreach ($entries as $entry) {
    if (is_array($entry) && ($entry['id'] ?? null) === 'demo-app') {
        $demo = $entry;
        break;
    }
}

if (!is_array($demo)) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_DEMO_APP_ENTRY_MISSING\n");
    exit(1);
}

if (($demo['kind'] ?? null) !== 'fullstack') {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_DEMO_APP_KIND_INVALID\n");
    exit(1);
}

if (($demo['root_path'] ?? null) !== 'sites/demo-app') {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_DEMO_APP_ROOT_INVALID\n");
    exit(1);
}

$cards = is_array($page['cards'] ?? null) ? $page['cards'] : [];
$renderedDemoCard = false;
foreach ($cards as $card) {
    $items = is_array($card['items'] ?? null) ? implode('\n', $card['items']) : '';
    if (is_array($card) && str_contains((string) ($card['title'] ?? ''), 'Demo OPUS Application') && str_contains($items, 'kind: fullstack')) {
        $renderedDemoCard = true;
        break;
    }
}
if (!$renderedDemoCard) {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_DEMO_APP_CARD_MISSING\n");
    exit(1);
}

$front = (string) file_get_contents($frontFile);
foreach ([
    'owasys_current_app',
    'select-app',
    'clear-app-context',
    'create-new-app',
    'Work on this app',
    'Current application',
    'OWASYS_CURRENT_APP_CONTEXT',
    'YOU ARE WORKING ON',
    'Visual navigation',
    'OWASYS_MERMAID_NAVIGATION',
    'flowchart LR',
    'click registry',
    'click structure',
    'Application context',
    "'/structure'",
    "'/data'",
    "'/workflows'",
    "'/security'",
    'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js',
] as $needle) {
    if (!str_contains($front, $needle)) {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_CONTEXT_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$css = (string) file_get_contents($cssFile);
foreach (['.ow-current-app', '.ow-current-app-hero', '.ow-context-panel', '.ow-mermaid-panel', '.ow-registry-card', '.ow-inline-form'] as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_CONTEXT_CSS_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$js = (string) file_get_contents($jsFile);
foreach (['window.mermaid', 'securityLevel', 'loose', 'startOnLoad'] as $needle) {
    if (!str_contains($js, $needle)) {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_CONTEXT_JS_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

echo "OWASYS_REGISTRY_NAMING_SMOKE_OK\n";
