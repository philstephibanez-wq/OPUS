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
    'sites/__contract_probe/application/architecture',
    'sites/__contract_probe/application/architecture/acl',
    'sites/__contract_probe/application/architecture/helpers',
    'sites/__contract_probe/application/architecture/css/architecture.css',
    'sites/__contract_probe/application/architecture/javascript/architecture.js',
    'sites/__contract_probe/application/architecture/local/fr/i18n.json',
    'sites/__contract_probe/application/architecture/models',
    'sites/__contract_probe/application/architecture/templates/index.score',
    'sites/__contract_probe/application/architecture/views',
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
    foreach (['sites/__contract_probe/public', 'sites/__contract_probe/resources', 'sites/__contract_probe/application/common', 'sites/__contract_probe/application/pages', 'sites/__contract_probe/application/config'] as $forbidden) {
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
if (($site['public_root'] ?? '') !== 'www' || ($site['asset_root'] ?? '') !== 'www/asset' || ($site['default_root'] ?? '') !== 'application/default') {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_SITE_ROOTS_INVALID\n");
    exit(1);
}
if (($site['css_inheritance'] ?? []) !== ['application/default/css', 'www/asset/themes/<theme>/css', 'application/<controller>/css']) {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_CSS_INHERITANCE_INVALID\n");
    exit(1);
}
if (($site['js_inheritance'] ?? []) !== ['application/default/javascript', 'www/asset/themes/<theme>/js', 'application/<controller>/javascript']) {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_JS_INHERITANCE_INVALID\n");
    exit(1);
}
$layout = $contents['sites/__contract_probe/application/default/templates/layout.score'] ?? '';
if (!str_contains($layout, '{{{ assets.css }}}') || !str_contains($layout, '{{{ assets.js }}}')) {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_LAYOUT_ASSET_SLOTS_MISSING\n");
    exit(1);
}
echo "OPUS_ETERNAL_ASAP_SITE_CONTRACT_SMOKE_OK\n";
