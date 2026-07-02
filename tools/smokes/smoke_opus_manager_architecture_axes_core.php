<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_ARCHITECTURE_AXES_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$doc = $root . '/DOC/OPUS_MANAGER_ARCHITECTURE_AXES.md';
$scope = $root . '/DOC/P7_OPS_FINAL_CLOSURE_SCOPE.md';

foreach ([$doc, $scope] as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('OPUS_MANAGER_ARCHITECTURE_AXES_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ([$doc, $scope] as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('OPUS_MANAGER_ARCHITECTURE_AXES_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'OPUS_MANAGER_ARCHITECTURE_AXES_CORE',
    'Axe technique',
    'Axe fonctionnel',
    'Frontend ≠ Frontoffice',
    'Backend ≠ Backoffice',
    'Un backoffice peut être client/server',
    'Un frontoffice peut aussi être client/server',
    'LogAndPlay',
    'Futur KB',
    'Create Site Wizard',
    'espace fonctionnel + architecture technique',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('OPUS_MANAGER_ARCHITECTURE_AXES_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_OPUS_MANAGER_ARCHITECTURE_AXES=OK' . PHP_EOL;
echo 'OPUS_MANAGER_ARCHITECTURE_AXES_CORE_SMOKE_OK' . PHP_EOL;
