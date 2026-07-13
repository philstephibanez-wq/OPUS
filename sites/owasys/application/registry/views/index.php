<?php
declare(strict_types=1);

/**
 * OWASYS registry view-model.
 * Data only: discovers OPUS applications from sites/*/config/site.json and merges the OWASYS registry seed.
 */

$owasysSiteRoot = isset($siteRoot) && is_string($siteRoot) ? $siteRoot : dirname(__DIR__, 3);
$repoRoot = dirname($owasysSiteRoot, 2);
$seedFile = $owasysSiteRoot . '/config/registry.seed.json';

$kindLabels = [
    'fullstack' => 'Fullstack',
    'frontend' => 'Frontend',
    'backend' => 'Backend',
    'package' => 'Package',
];

$normalizeKind = static function (mixed $kind): string {
    $value = strtolower(trim((string) $kind));
    if (in_array($value, ['fullstack', 'frontend', 'backend', 'package'], true)) {
        return $value;
    }

    return 'fullstack';
};

$entries = [];
$registerEntry = static function (array $entry) use (&$entries, $normalizeKind): void {
    $id = trim((string) ($entry['id'] ?? $entry['site_id'] ?? ''));
    if ($id === '') {
        return;
    }

    $entries[$id] = array_merge($entries[$id] ?? [], [
        'id' => $id,
        'slug' => (string) ($entry['slug'] ?? $id),
        'name' => (string) ($entry['name'] ?? $entry['site_name'] ?? $id),
        'kind' => $normalizeKind($entry['kind'] ?? 'fullstack'),
        'root_path' => (string) ($entry['root_path'] ?? ('sites/' . $id)),
        'public_root' => (string) ($entry['public_root'] ?? 'www'),
        'default_locale' => (string) ($entry['default_locale'] ?? 'fr'),
        'theme' => (string) ($entry['theme'] ?? 'default'),
        'status' => (string) ($entry['status'] ?? 'discovered'),
        'blueprint' => (string) ($entry['blueprint'] ?? 'unknown'),
        'generated_by' => (string) ($entry['generated_by'] ?? 'unknown'),
        'role' => (string) ($entry['role'] ?? 'standard-opus-application'),
    ]);
};

if (is_file($seedFile)) {
    $seed = json_decode((string) file_get_contents($seedFile), true);
    if (is_array($seed) && ($seed['contract'] ?? null) === 'OWASYS_REGISTRY_SEED_V1') {
        foreach ((array) ($seed['applications'] ?? []) as $seedEntry) {
            if (is_array($seedEntry)) {
                $registerEntry($seedEntry);
            }
        }
    }
}

foreach (glob($repoRoot . '/sites/*/config/site.json') ?: [] as $siteJsonFile) {
    if (!is_string($siteJsonFile) || !is_file($siteJsonFile)) {
        continue;
    }

    $site = json_decode((string) file_get_contents($siteJsonFile), true);
    if (!is_array($site) || ($site['contract'] ?? null) !== 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL') {
        continue;
    }

    $siteId = (string) ($site['site_id'] ?? basename(dirname(dirname($siteJsonFile))));
    $siteRootRelative = 'sites/' . basename(dirname(dirname($siteJsonFile)));
    $manifestFile = dirname($siteJsonFile) . '/owasys-creation-manifest.json';
    $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : [];
    $manifest = is_array($manifest) ? $manifest : [];
    $validation = is_array($manifest['validation'] ?? null) ? $manifest['validation'] : [];

    $registerEntry([
        'id' => $siteId,
        'slug' => $siteId,
        'name' => (string) ($site['site_name'] ?? $siteId),
        'kind' => $site['kind'] ?? 'fullstack',
        'root_path' => $siteRootRelative,
        'public_root' => $site['public_root'] ?? 'www',
        'default_locale' => $site['default_locale'] ?? 'fr',
        'theme' => $site['theme'] ?? 'default',
        'status' => (string) ($validation['status'] ?? (($site['generated_by'] ?? null) === 'owasys' ? 'generated' : 'discovered')),
        'blueprint' => $manifest['blueprint'] ?? $site['blueprint'] ?? 'unknown',
        'generated_by' => $site['generated_by'] ?? ($manifest['generator'] ?? 'unknown'),
        'role' => $site['role'] ?? 'standard-opus-application',
    ]);
}

ksort($entries);
$registryEntries = array_values($entries);

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
        ],
    ];
}

return [
    'title' => 'Application Registry',
    'badge' => 'Registry',
    'summary' => 'Registry of OPUS sites and packages managed by OWASYS.',
    'sections' => [
        'Registered applications',
        'Application types',
        'Blueprints',
        'Git and Composer metadata',
    ],
    'registry_entries' => $registryEntries,
    'cards' => $cards,
    'contracts' => [
        'OWASYS_APPLICATION_TYPES_V1',
        'OWASYS_REGISTRY_SEED_V1',
        'OPUS_MODEL_SCHEMA_V1',
    ],
    'actions' => [
        'Register an existing OPUS application',
        'Create a new application draft',
        'Attach Git remote and Composer metadata',
    ],
];
