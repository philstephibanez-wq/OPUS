<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';

$siteFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
$routesFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.json';
$seedFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'registry.seed.json';
$fsmFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'owasys-navigation.fsm.json';
$registryView = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . 'registry' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.php';
$frontFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';
$cssFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'owasys.css';
$jsFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'owasys.js';

foreach ([$siteFile, $routesFile, $seedFile, $fsmFile, $registryView, $frontFile, $cssFile, $jsFile] as $requiredFile) {
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
$fsm = json_decode((string) file_get_contents($fsmFile), true);

if (!is_array($site) || ($site['states_root'] ?? null) !== 'application/states' || ($site['dispatch_model'] ?? null) !== 'state-first') {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_SITE_STATE_ROOT_INVALID\n");
    exit(1);
}

if (!is_array($routes) || ($routes['dispatch_model'] ?? null) !== 'state-first') {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_ROUTES_JSON_INVALID\n");
    exit(1);
}

if (!is_array($seed) || ($seed['contract'] ?? null) !== 'OWASYS_REGISTRY_SEED_V1') {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_SEED_INVALID\n");
    exit(1);
}

if (!is_array($fsm) || ($fsm['contract'] ?? null) !== 'OWASYS_NAVIGATION_FSM_V1') {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_FSM_INVALID\n");
    exit(1);
}

$navigation = is_array($site['navigation'] ?? null) ? $site['navigation'] : [];
if (($navigation['fsm'] ?? null) !== 'config/owasys-navigation.fsm.json') {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_SITE_FSM_POINTER_INVALID\n");
    exit(1);
}

$states = [];
foreach ((array) ($fsm['states'] ?? []) as $state) {
    if (is_array($state) && isset($state['id'])) {
        $states[(string) $state['id']] = $state;
    }
}

foreach (['home', 'registry', 'structure', 'data', 'workflows', 'security', 'build', 'account', 'login'] as $stateId) {
    if (!isset($states[$stateId])) {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_FSM_STATE_MISSING: {$stateId}\n");
        exit(1);
    }
    $view = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . $stateId . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.php';
    if (!is_file($view)) {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_STATE_VIEW_MISSING: {$stateId}\n");
        exit(1);
    }
}

foreach (['structure', 'data', 'workflows', 'security'] as $stateId) {
    if (($states[$stateId]['requires_current_app'] ?? null) !== true) {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_FSM_GUARD_MISSING: {$stateId}\n");
        exit(1);
    }
}

$legacyRoots = ['home', 'registry', 'structure', 'data', 'workflows', 'security', 'build', 'account', 'login', 'applications'];
foreach ($legacyRoots as $legacyRoot) {
    $legacyPath = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . $legacyRoot;
    if (is_dir($legacyPath)) {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_LEGACY_STATE_ROOT_PRESENT: {$legacyRoot}\n");
        exit(1);
    }
}

$matched = false;
foreach ((array) ($routes['routes'] ?? []) as $route) {
    if (!is_array($route) || ($route['path'] ?? null) !== '/applications') {
        continue;
    }

    $matched = true;
    if (($route['state'] ?? null) !== 'registry' || ($route['controller'] ?? null) !== 'registry') {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_ROUTE_STATE_INVALID\n");
        exit(1);
    }
    if (($route['view'] ?? null) !== 'application/states/registry/views/index.php') {
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

if (!is_array($demo) || ($demo['kind'] ?? null) !== 'fullstack' || ($demo['root_path'] ?? null) !== 'sites/demo-app') {
    fwrite(STDERR, "OWASYS_REGISTRY_NAMING_DEMO_APP_ENTRY_INVALID\n");
    exit(1);
}

$front = (string) file_get_contents($frontFile);
foreach ([
    'OWASYS_NAVIGATION_FSM_V1',
    'owasys-navigation.fsm.json',
    'application/states',
    'state-first',
    '$statesByRoute',
    '$requiresCurrentApp',
    'requires_current_app',
    'owasys_current_app',
    'select-app',
    'clear-app-context',
    'create-new-app',
    'Work on this app',
    'OWASYS_REGISTRY_APP_TREE',
    'Application tree',
    'Current application',
    'OWASYS_CURRENT_APP_CONTEXT',
    'YOU ARE WORKING ON',
    'Visual FSM navigation',
    'OWASYS_MERMAID_NAVIGATION',
    'flowchart LR',
    'click ' . "' . \$id",
    'Application context',
    'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js',
] as $needle) {
    if (!str_contains($front, $needle)) {
        fwrite(STDERR, "OWASYS_REGISTRY_NAMING_CONTEXT_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$css = (string) file_get_contents($cssFile);
foreach (['.ow-current-app', '.ow-current-app-hero', '.ow-context-panel', '.ow-mermaid-panel', '.ow-app-tree', '.ow-tree-root', '.ow-tree-app', '.ow-registry-card', '.ow-inline-form'] as $needle) {
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
