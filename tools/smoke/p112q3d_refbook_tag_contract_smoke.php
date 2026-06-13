<?php

declare(strict_types=1);

/**
 * P112Q3D RefBook tag contract smoke.
 *
 * Public CLI smoke.
 * Role:
 *   Verify that the RefBook tag contract scanner and strict test entrypoint are
 *   present, syntax-valid and expose the expected stable markers.
 */
$root = dirname(__DIR__, 2);
$script = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'quality' . DIRECTORY_SEPARATOR . 'p112q3d_refbook_tag_contract.php';
$test = $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Contract' . DIRECTORY_SEPARATOR . 'RefBookTagContractTest.php';

foreach ([$script, $test] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, 'P112Q3D_SMOKE_FAILED: FILE_MISSING: ' . $path . PHP_EOL);
        exit(1);
    }
}

$content = file_get_contents($script);
$testContent = file_get_contents($test);
if (!is_string($content) || !is_string($testContent)) {
    fwrite(STDERR, 'P112Q3D_SMOKE_FAILED: FILE_READ_FAILED' . PHP_EOL);
    exit(1);
}

$requiredMarkers = [
    'P112Q3D_REFBOOK_TAG_CONTRACT_AUDIT_OK',
    'P112Q3D_REFBOOK_TAG_CONTRACT_STRICT_FAILED',
    'OPUS_P112Q3D_STRICT',
    'OPUS_REFBOOK:',
    'END_OPUS_REFBOOK',
    'p112q3d_refbook_tag_contract',
    'Class-level tags and method-level tags are separate',
];

foreach ($requiredMarkers as $marker) {
    if (!str_contains($content, $marker)) {
        fwrite(STDERR, 'P112Q3D_SMOKE_FAILED: MARKER_MISSING: ' . $marker . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($testContent, 'P112Q3DRefBookTagContract') || !str_contains($testContent, 'run(true)')) {
    fwrite(STDERR, 'P112Q3D_SMOKE_FAILED: STRICT_TEST_MARKER_MISSING' . PHP_EOL);
    exit(1);
}

foreach ([$script, $test] as $path) {
    $lintOutput = [];
    $exitCode = 0;
    exec('php -l ' . escapeshellarg($path), $lintOutput, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, 'P112Q3D_SMOKE_FAILED: PHP_LINT_FAILED: ' . $path . PHP_EOL . implode(PHP_EOL, $lintOutput) . PHP_EOL);
        exit(1);
    }
}

echo 'P112Q3D_REFBOOK_TAG_CONTRACT_SMOKE_OK' . PHP_EOL;
