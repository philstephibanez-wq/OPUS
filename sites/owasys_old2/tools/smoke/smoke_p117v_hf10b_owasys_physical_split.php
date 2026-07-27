<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
$required = [
    'Opus/Security/Runtime/RuntimeSecretStore.php',
    'Opus/Security/Runtime/RuntimeSecretStoreInterface.php',
    'sites/owasys/application/shared/Application.php',
    'sites/owasys/application/shared/RuntimeInterface.php',
    'sites/owasys/application/front/bootstrap.php',
    'sites/owasys/application/front/Runtime.php',
    'sites/owasys/application/front/default/controllers/RuntimeController.php',
    'sites/owasys/application/front/default/services/ScorePageRenderer.php',
    'sites/owasys/application/front/modules/registry/controllers/RegistryController.php',
    'sites/owasys/application/front/modules/creation/controllers/CreationController.php',
    'sites/owasys/application/back/bootstrap.php',
    'sites/owasys/application/back/Runtime.php',
    'sites/owasys/application/back/api/controllers/BackendApiController.php',
    'sites/owasys/www/index.php',
];
foreach ($required as $relative) {
    if (!is_file($root . '/' . $relative)) {
        throw new RuntimeException(
            'P117V_HF10B_REQUIRED_FILE_MISSING:' . $relative
        );
    }
}

$site = json_decode(
    (string) file_get_contents(
        $root . '/sites/owasys/config/site.json'
    ),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$layout = is_array($site['application_layers'] ?? null)
    ? $site['application_layers']
    : [];
if (($layout['contract'] ?? null) !== 'OPUS_APPLICATION_LAYER_LAYOUT_V1'
    || ($layout['shared'] ?? null) !== 'application/shared'
    || ($layout['front'] ?? null) !== 'application/front'
    || ($layout['back'] ?? null) !== 'application/back'
    || ($layout['fullstack'] ?? null) !== 'composition'
    || ($layout['full_directory_forbidden'] ?? false) !== true) {
    throw new RuntimeException('P117V_HF10B_LAYOUT_CONTRACT_INVALID');
}
$modes = is_array($site['runtime_modes'] ?? null)
    ? $site['runtime_modes']
    : [];
sort($modes, SORT_STRING);
if ($modes !== ['back', 'front']
    || ($site['runtime_secrets']['contract'] ?? null)
        !== 'OPUS_RUNTIME_SECRET_BINDING_V1'
    || ($site['runtime_diagnostics']['logs']['front'] ?? null)
        !== 'var/logs/owasys-frontend.log'
    || ($site['runtime_diagnostics']['logs']['back'] ?? null)
        !== 'var/logs/rcp-backend.log') {
    throw new RuntimeException('P117V_HF10B_RUNTIME_POLICY_INVALID');
}

$runtime = is_array($site['runtime'] ?? null) ? $site['runtime'] : [];
if (($runtime['contract'] ?? null) !== 'OPUS_APPLICATION_SINGLETON_V2'
    || ($runtime['file'] ?? null) !== 'application/shared/Application.php'
    || ($runtime['bootstraps']['front'] ?? null)
        !== 'application/front/bootstrap.php'
    || ($runtime['bootstraps']['back'] ?? null)
        !== 'application/back/bootstrap.php') {
    throw new RuntimeException('P117V_HF10B_SINGLETON_CONTRACT_INVALID');
}

$front = (string) file_get_contents(
    $root . '/sites/owasys/application/front/bootstrap.php'
);
$back = (string) file_get_contents(
    $root . '/sites/owasys/application/back/bootstrap.php'
);
$entry = (string) file_get_contents(
    $root . '/sites/owasys/www/index.php'
);
if (str_contains($front, 'BackendApiController.php')
    || str_contains($back, 'RuntimeController.php')
    || !str_contains($front, 'application/front/modules/registry')
    || !str_contains($entry, 'application/front/bootstrap.php')
    || !str_contains($entry, 'application/back/bootstrap.php')) {
    throw new RuntimeException('P117V_HF10B_RUNTIME_ISOLATION_INVALID');
}

$class = (string) file_get_contents(
    $root . '/Opus/Security/Runtime/RuntimeSecretStore.php'
);
$interface = (string) file_get_contents(
    $root . '/Opus/Security/Runtime/RuntimeSecretStoreInterface.php'
);
if (!str_contains($class, 'implements RuntimeSecretStoreInterface')
    || !str_contains($interface, 'OpusFrameworkComponentInterface')
    || !str_contains($interface, 'OpusExceptionAwareInterface')
    || !str_contains($interface, 'OpusProfilerAwareInterface')
    || !str_contains($interface, 'OpusSelfDocumentingInterface')) {
    throw new RuntimeException('P117V_HF10B_FRAMEWORK_CONTRACT_INVALID');
}

$service = (string) file_get_contents(
    $root . '/Opus/Console/Service/SiteCommandService.php'
);
if (!str_contains($service, 'RuntimeSecretStore::forPath')
    || !str_contains($service, 'process.starting')
    || !str_contains($service, 'OPUS_APPLICATION_RUNTIME_MODE')) {
    throw new RuntimeException('P117V_HF10B_SERVE_BOOTSTRAP_INVALID');
}

fwrite(STDOUT, "P117V_HF10B_OWASYS_PHYSICAL_SPLIT_SMOKE_OK\n");
