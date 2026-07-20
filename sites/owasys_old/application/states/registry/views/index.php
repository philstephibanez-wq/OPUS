<?php
declare(strict_types=1);

use Opus\Owasys\RegistryRepository;

$owasysSiteRoot = isset($siteRoot) && is_string($siteRoot) ? $siteRoot : dirname(__DIR__, 4);
$repoRoot = dirname($owasysSiteRoot, 2);
$seedFile = $owasysSiteRoot . '/config/registry.seed.json';
$viewRegistryDatabaseRelative = isset($owasysRegistryDatabaseRelative) && is_string($owasysRegistryDatabaseRelative)
    ? $owasysRegistryDatabaseRelative
    : null;
$tr = isset($t) && is_callable($t)
    ? $t
    : static fn (string $key, string $fallback = ''): string => $fallback !== '' ? $fallback : $key;

$kindLabels = [
    'fullstack' => 'Fullstack',
    'frontend' => 'Frontend',
    'backend' => 'Backend',
    'package' => 'Package',
];

$registryRepository = isset($owasysRegistryRepository) && $owasysRegistryRepository instanceof RegistryRepository
    ? $owasysRegistryRepository
    : RegistryRepository::forOwasysSite($owasysSiteRoot, $repoRoot, $viewRegistryDatabaseRelative);
$registrySync = isset($owasysRegistrySync) && is_array($owasysRegistrySync)
    ? $owasysRegistrySync
    : $registryRepository->synchronize($seedFile);
$registryEntries = $registryRepository->entries();

$cards = [];
foreach ($registryEntries as $entry) {
    $kind = (string) ($entry['kind'] ?? 'fullstack');
    $cards[] = [
        'title' => (string) ($entry['name'] ?? $entry['id']),
        'body' => $tr('registry.registered_target', 'Registered OPUS target') . ': ' . (string) ($entry['root_path'] ?? 'unknown'),
        'items' => [
            'id: ' . (string) ($entry['id'] ?? 'unknown'),
            'type: ' . ($kindLabels[$kind] ?? $kind),
            'kind: ' . $kind,
            'status: ' . (string) ($entry['status'] ?? 'unknown'),
            'blueprint: ' . (string) ($entry['blueprint'] ?? 'unknown'),
            'theme: ' . (string) ($entry['theme'] ?? 'unknown'),
            $tr('registry.source', 'source') . ': ' . (string) ($entry['source'] ?? 'sqlite'),
        ],
    ];
}

return [
    'state' => 'registry',
    'title' => $tr('menu.applications', 'Application Registry'),
    'badge' => 'Registry',
    'summary' => $tr('registry.choose_or_create', 'Registry of OPUS sites and packages managed by OWASYS.'),
    'sections' => [$tr('registry.application_tree', 'Registered applications'), 'Application types', 'Blueprints', 'Git and Composer metadata'],
    'registry_entries' => $registryEntries,
    'registry_sync' => $registrySync,
    'registry_database' => $registryRepository->relativeDatabasePath(),
    'cards' => $cards,
    'contracts' => ['OWASYS_APPLICATION_TYPES_V1', 'OWASYS_REGISTRY_SEED_V1', 'OWASYS_REGISTRY_SQLITE_V1', 'OPUS_MODEL_SCHEMA_V1'],
    'actions' => ['Register an existing OPUS application', 'Create a new application draft', 'Attach Git remote and Composer metadata'],
];