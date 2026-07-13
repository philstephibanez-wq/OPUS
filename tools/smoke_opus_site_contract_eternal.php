<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Scaffold\ScaffoldEntry;
use Opus\Scaffold\SiteScaffoldPlan;

$plan = SiteScaffoldPlan::forSite('__contract_probe');
$paths = [];
$contents = [];
foreach ($plan->entries() as $entry) {
    if (!$entry instanceof ScaffoldEntry) {
        fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_UNEXPECTED_ENTRY_CLASS\n");
        exit(1);
    }
    $path = str_replace('\\', '/', $entry->relativePath);
    $paths[$path] = true;
    if ($entry->content !== null) {
        $contents[$path] = $entry->content;
    }
}
$required = [
    'sites/__contract_probe/config',
    'sites/__contract_probe/config/site.json',
    'sites/__contract_probe/config/routes.json',
    'sites/__contract_probe/config/application.fsm.json',
    'sites/__contract_probe/config/fsm.json',
    'sites/__contract_probe/application',
    'sites/__contract_probe/application/default',
    'sites/__contract_probe/application/default/acl',
    'sites/__contract_probe/application/default/helpers',
    'sites/__contract_probe/application/default/css',
    'sites/__contract_probe/application/default/css/default.css',
    'sites/__contract_probe/application/default/javascript',
    'sites/__contract_probe/application/default/javascript/default.js',
    'sites/__contract_probe/application/default/local/fr/i18n.json',
    'sites/__contract_probe/application/default/models',
    'sites/__contract_probe/application/default/templates/layout.score',
    'sites/__contract_probe/application/default/templates/components/header.score',
    'sites/__contract_probe/application/default/views',
    'sites/__contract_probe/application/states',
    'sites/__contract_probe/application/states/architecture',
    'sites/__contract_probe/application/states/architecture/acl',
    'sites/__contract_probe/application/states/architecture/helpers',
    'sites/__contract_probe/application/states/architecture/css/architecture.css',
    'sites/__contract_probe/application/states/architecture/javascript/architecture.js',
    'sites/__contract_probe/application/states/architecture/local/fr/i18n.json',
    'sites/__contract_probe/application/states/architecture/models',
    'sites/__contract_probe/application/states/architecture/templates/index.score',
    'sites/__contract_probe/application/states/architecture/views',
    'sites/__contract_probe/application/states/architecture/views/index.php',
    'sites/__contract_probe/www',
    'sites/__contract_probe/www/index.php',
    'sites/__contract_probe/www/asset/css',
    'sites/__contract_probe/www/asset/js',
    'sites/__contract_probe/www/asset/themes/starter/css/theme.css',
    'sites/__contract_probe/www/asset/themes/starter/js/theme.js',
];
foreach ($required as $path) {
    if (!isset($paths[$path])) {
        fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_PATH_MISSING: {$path}\n");
        exit(1);
    }
}
foreach (array_keys($paths) as $path) {
    foreach (['sites/__contract_probe/public', 'sites/__contract_probe/resources', 'sites/__contract_probe/application/common', 'sites/__contract_probe/application/pages', 'sites/__contract_probe/application/config', 'sites/__contract_probe/application/home', 'sites/__contract_probe/application/architecture'] as $forbidden) {
        if ($path === $forbidden || str_starts_with($path, $forbidden . '/')) {
            fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_FORBIDDEN_PATH: {$path}\n");
            exit(1);
        }
    }
}
$site = json_decode($contents['sites/__contract_probe/config/site.json'] ?? '', true);
if (!is_array($site) || ($site['contract'] ?? '') !== 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL') {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_SITE_JSON_INVALID\n");
    exit(1);
}
if (($site['public_root'] ?? '') !== 'www' || ($site['asset_root'] ?? '') !== 'www/asset' || ($site['default_root'] ?? '') !== 'application/default' || ($site['states_root'] ?? '') !== 'application/states') {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_SITE_ROOTS_INVALID\n");
    exit(1);
}
if (($site['dispatch_model'] ?? '') !== 'state-first') {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_DISPATCH_MODEL_INVALID\n");
    exit(1);
}
if (($site['css_inheritance'] ?? []) !== ['application/default/css', 'www/asset/themes/<theme>/css', 'application/states/<state>/css']) {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_CSS_INHERITANCE_INVALID\n");
    exit(1);
}
if (($site['js_inheritance'] ?? []) !== ['application/default/javascript', 'www/asset/themes/<theme>/js', 'application/states/<state>/javascript']) {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_JS_INHERITANCE_INVALID\n");
    exit(1);
}
$routes = json_decode($contents['sites/__contract_probe/config/routes.json'] ?? '', true);
if (!is_array($routes) || ($routes['dispatch_model'] ?? '') !== 'state-first') {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_ROUTES_INVALID\n");
    exit(1);
}
foreach ((array) ($routes['routes'] ?? []) as $route) {
    if (!is_array($route) || !isset($route['state']) || !str_contains((string) ($route['view'] ?? ''), 'application/states/')) {
        fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_ROUTE_STATE_INVALID\n");
        exit(1);
    }
}
$applicationFsm = json_decode($contents['sites/__contract_probe/config/application.fsm.json'] ?? '', true);
if (!is_array($applicationFsm) || ($applicationFsm['contract'] ?? '') !== 'OPUS_APPLICATION_FSM_V1' || ($applicationFsm['dispatch_model'] ?? '') !== 'state-first' || empty($applicationFsm['states']) || !array_key_exists('transitions', $applicationFsm)) {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_APPLICATION_FSM_INVALID\n");
    exit(1);
}
$fsm = json_decode($contents['sites/__contract_probe/config/fsm.json'] ?? '', true);
if (!is_array($fsm) || ($fsm['contract'] ?? '') !== 'OPUS_FSM_REGISTRY_V1' || empty($fsm['states']) || !array_key_exists('transitions', $fsm)) {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_FSM_INVALID\n");
    exit(1);
}
$layout = $contents['sites/__contract_probe/application/default/templates/layout.score'] ?? '';
if (!str_contains($layout, '{{{ assets.css }}}') || !str_contains($layout, '{{{ assets.js }}}')) {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_LAYOUT_ASSET_SLOTS_MISSING\n");
    exit(1);
}
echo "OPUS_ETERNAL_ASAP_SITE_CONTRACT_SMOKE_OK\n";
