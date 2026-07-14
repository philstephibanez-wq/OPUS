<?php
declare(strict_types=1);

use Opus\Owasys\RegistryRepository;

$owasysSiteRoot = isset($siteRoot) && is_string($siteRoot) ? $siteRoot : dirname(__DIR__, 4);
$repoRoot = dirname($owasysSiteRoot, 2);
$seedFile = $owasysSiteRoot . '/config/registry.seed.json';
$registryDatabaseRelative = isset($owasysRegistryDatabaseRelative) && is_string($owasysRegistryDatabaseRelative)
    ? $owasysRegistryDatabaseRelative
    : null;

$kindLabels = [
    'fullstack' => 'Fullstack',
    'frontend' => 'Frontend',
    'backend' => 'Backend',
    'package' => 'Package',
];

$registryRepository = RegistryRepository::forOwasysSite($owasysSiteRoot, $repoRoot, $registryDatabaseRelative);
$registrySync = $registryRepository->synchronize($seedFile);
$registryEntries = $registryRepository->entries();

$cards = [];
foreach ($registryEntries as $entry) {
    $kind = (string) ($entry['kind'] ?? 'fullstack');
    $cards[] = [
        'title' => (string) ($entry['name'] ?? $entry['id']),
        'body' => 'Registered OPUS target: ' . (string) ($entry['root_path'] ?? 'unknown'),
        'items' => [
            'id: ' . (string) ($entry['id'] ?? 'unknown'),
            'type: ' . ($kindLabels[$kind] ?? $kind),
            'kind: ' . $kind,
            'status: ' . (string) ($entry['status'] ?? 'unknown'),
            'blueprint: ' . (string) ($entry['blueprint'] ?? 'unknown'),
            'theme: ' . (string) ($entry['theme'] ?? 'unknown'),
            'source: ' . (string) ($entry['source'] ?? 'sqlite'),
        ],
    ];
}

return [
    'state' => 'registry',
    'title' => 'Application Registry',
    'badge' => 'Registry',
    'summary' => 'Registry of OPUS sites and packages managed by OWASYS.',
    'sections' => ['Registered applications', 'Application types', 'Blueprints', 'Git and Composer metadata'],
    'registry_entries' => $registryEntries,
    'registry_sync' => $registrySync,
    'registry_database' => $registryRepository->relativeDatabasePath(),
    'cards' => $cards,
    'contracts' => ['OWASYS_APPLICATION_TYPES_V1', 'OWASYS_REGISTRY_SEED_V1', 'OWASYS_REGISTRY_SQLITE_V1', 'OPUS_MODEL_SCHEMA_V1'],
    'actions' => ['Register an existing OPUS application', 'Create a new application draft', 'Attach Git remote and Composer metadata'],
];
