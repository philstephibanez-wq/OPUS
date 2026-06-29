<?php
declare(strict_types=1);

$path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'composer.json';
$data = json_decode((string) file_get_contents($path), true);
if (!is_array($data)) {
    throw new RuntimeException('COMPOSER_JSON_INVALID');
}

$repositories = isset($data['repositories']) && is_array($data['repositories']) ? array_values($data['repositories']) : [];
$hasPackagesPath = false;
foreach ($repositories as $repository) {
    if (!is_array($repository)) {
        continue;
    }
    if (($repository['type'] ?? '') === 'path' && str_replace('\\', '/', (string) ($repository['url'] ?? '')) === 'packages/*') {
        $hasPackagesPath = true;
    }
}

if (!$hasPackagesPath) {
    array_unshift($repositories, [
        'type' => 'path',
        'url' => 'packages/*',
        'options' => [
            'symlink' => true,
        ],
    ]);
}
$data['repositories'] = $repositories;

$extra = isset($data['extra']) && is_array($data['extra']) ? $data['extra'] : [];
$opus = isset($extra['opus']) && is_array($extra['opus']) ? $extra['opus'] : [];
$opus['packages_directory'] = 'packages';
$opus['application_package_type'] = 'opus-application';
$extra['opus'] = $opus;
$data['extra'] = $extra;

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
file_put_contents($path, $json);
echo "COMPOSER_PACKAGES_DIRECTORY_UPDATED\n";
