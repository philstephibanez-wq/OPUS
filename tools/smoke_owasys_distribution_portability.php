<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$contractFile = $root . '/sites/owasys/config/distribution.json';
if (!is_file($contractFile)) {
    fwrite(STDERR, "OWASYS_DISTRIBUTION_CONTRACT_MISSING\n");
    exit(1);
}

$contract = json_decode((string) file_get_contents($contractFile), true);
if (!is_array($contract)
    || ($contract['contract'] ?? null) !== 'OWASYS_DISTRIBUTION_V1'
    || ($contract['role'] ?? null) !== 'opus-user-deliverable'
    || ($contract['portable'] ?? null) !== true
    || ($contract['machine_specific_paths_allowed'] ?? null) !== false
    || ($contract['local_stack_dependency_allowed'] ?? null) !== false) {
    fwrite(STDERR, "OWASYS_DISTRIBUTION_CONTRACT_INVALID\n");
    exit(1);
}

$systems = $contract['supported_operating_system_families'] ?? null;
if (!is_array($systems) || !in_array('windows', $systems, true) || !in_array('linux', $systems, true)) {
    fwrite(STDERR, "OWASYS_DISTRIBUTION_OS_SUPPORT_INVALID\n");
    exit(1);
}

$environment = is_array($contract['environment_contract'] ?? null) ? $contract['environment_contract'] : [];
if (($environment['variable'] ?? null) !== 'OPUS_ENV'
    || ($environment['production_value'] ?? null) !== 'prod'
    || ($environment['machine_identity_is_environment'] ?? null) !== false
    || ($environment['development_values'] ?? null) !== ['dev', 'local', 'development']) {
    fwrite(STDERR, "OWASYS_DISTRIBUTION_ENVIRONMENT_CONTRACT_INVALID\n");
    exit(1);
}

$generated = is_array($contract['generated_application_contract'] ?? null) ? $contract['generated_application_contract'] : [];
foreach ([
    'portable' => true,
    'relative_paths_only' => true,
    'profiler_mandatory' => true,
    'profiler_development_only' => true,
    'profiler_production_available' => false,
] as $key => $expected) {
    if (($generated[$key] ?? null) !== $expected) {
        fwrite(STDERR, "OWASYS_DISTRIBUTION_GENERATED_APP_CONTRACT_INVALID:{$key}\n");
        exit(1);
    }
}

$roots = [
    $root . '/Opus/Owasys',
    $root . '/sites/owasys',
    $root . '/bin/opus',
];
$forbiddenMarkers = [
    'H:\\OPUS',
    'H:/OPUS',
    'UwAmp',
    'PC gamer HP',
    'HP gamer',
];

$files = [];
foreach ($roots as $path) {
    if (is_file($path)) {
        $files[] = $path;
        continue;
    }
    if (!is_dir($path)) {
        fwrite(STDERR, 'OWASYS_DISTRIBUTION_REQUIRED_ROOT_MISSING:' . $path . PHP_EOL);
        exit(1);
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $files[] = $item->getPathname();
        }
    }
}

foreach ($files as $file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($extension, ['', 'php', 'json', 'js', 'css', 'score', 'md', 'txt'], true)) {
        continue;
    }
    $source = (string) file_get_contents($file);
    foreach ($forbiddenMarkers as $marker) {
        if (str_contains($source, $marker)) {
            fwrite(STDERR, 'OWASYS_DISTRIBUTION_MACHINE_COUPLING_FOUND:' . $marker . ':' . str_replace('\\', '/', $file) . PHP_EOL);
            exit(1);
        }
    }
}

echo "OWASYS_DISTRIBUTION_PORTABILITY_SMOKE_OK\n";
