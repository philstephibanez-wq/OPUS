<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Scaffold\SiteScaffoldPlan;

$plan = SiteScaffoldPlan::forSite('__contract_probe');
$paths = [];
$contents = [];

foreach ($plan->entries() as $entry) {
    $path = method_exists($entry, 'path') ? $entry->path() : null;
    if (!is_string($path)) {
        $ref = new ReflectionObject($entry);
        if ($ref->hasProperty('path')) {
            $property = $ref->getProperty('path');
            $property->setAccessible(true);
            $path = (string) $property->getValue($entry);
        }
    }

    if ($path !== null) {
        $paths[$path] = true;
        $contents[$path] = method_exists($entry, 'content') ? $entry->content() : '';
        if ($contents[$path] === '' || !is_string($contents[$path])) {
            $ref = new ReflectionObject($entry);
            if ($ref->hasProperty('content')) {
                $property = $ref->getProperty('content');
                $property->setAccessible(true);
                $value = $property->getValue($entry);
                $contents[$path] = is_string($value) ? $value : '';
            }
        }
    }
}

$required = [
    'sites/__contract_probe/config',
    'sites/__contract_probe/config/site.json',
    'sites/__contract_probe/config/routes.json',
    'sites/__contract_probe/application/default/acl',
    'sites/__contract_probe/application/default/helpers',
    'sites/__contract_probe/application/default/css',
    'sites/__contract_probe/application/default/css/default.css',
    'sites/__contract_probe/application/default/javascript',
    'sites/__contract_probe/application/default/javascript/default.js',
    'sites/__contract_probe/application/default/local/fr',
    'sites/__contract_probe/application/default/local/fr/i18n.json',
    'sites/__contract_probe/application/default/models',
    'sites/__contract_probe/application/default/templates/layout.score',
    'sites/__contract_probe/application/default/templates/components/header.score',
    'sites/__contract_probe/application/default/views',
    'sites/__contract_probe/application/architecture/css',
    'sites/__contract_probe/application/architecture/javascript',
    'sites/__contract_probe/application/architecture/local/fr/i18n.json',
    'sites/__contract_probe/application/architecture/templates/index.score',
    'sites/__contract_probe/application/architecture/views',
    'sites/__contract_probe/www',
    'sites/__contract_probe/www/index.php',
    'sites/__contract_probe/www/asset/css',
    'sites/__contract_probe/www/asset/js',
    'sites/__contract_probe/www/asset/themes/starter/css',
    'sites/__contract_probe/www/asset/themes/starter/js',
];

foreach ($required as $path) {
    if (!isset($paths[$path])) {
        fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_PATH_MISSING: {$path}\n");
        exit(1);
    }
}

$forbiddenPrefixes = [
    'sites/__contract_probe/public',
    'sites/__contract_probe/resources',
    'sites/__contract_probe/application/common',
    'sites/__contract_probe/application/pages',
    'sites/__contract_probe/application/config',
];

foreach (array_keys($paths) as $path) {
    foreach ($forbiddenPrefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_FORBIDDEN_PATH: {$path}\n");
            exit(1);
        }
    }
}

$siteConfig = json_decode($contents['sites/__contract_probe/config/site.json'] ?? '', true);
if (!is_array($siteConfig)) {
    fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_SITE_JSON_INVALID\n");
    exit(1);
}

$expected = [
    'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    'public_root' => 'www',
    'asset_root' => 'www/asset',
    'default_root' => 'application/default',
    'theme' => 'starter',
];

foreach ($expected as $key => $value) {
    if (($siteConfig[$key] ?? null) !== $value) {
        fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_SITE_JSON_VALUE_INVALID: {$key}\n");
        exit(1);
    }
}

$layout = $contents['sites/__contract_probe/application/default/templates/layout.score'] ?? '';
foreach (['{{{ assets.css }}}', '{{{ assets.js }}}'] as $needle) {
    if (!str_contains($layout, $needle)) {
        fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_LAYOUT_ASSET_SLOT_MISSING: {$needle}\n");
        exit(1);
    }
}

$front = $contents['sites/__contract_probe/www/index.php'] ?? '';
foreach ([
    'application/default/css',
    'asset/themes',
    'application/\' . $controller . \'/css',
    'application/default/javascript',
    'application/\' . $controller . \'/javascript',
    'application/default/local',
] as $needle) {
    if (!str_contains($front, $needle)) {
        fwrite(STDERR, "OPUS_ETERNAL_CONTRACT_FRONT_CONTROLLER_MECHANISM_MISSING: {$needle}\n");
        exit(1);
    }
}

echo "OPUS_ETERNAL_ASAP_SITE_CONTRACT_SMOKE_OK\n";
