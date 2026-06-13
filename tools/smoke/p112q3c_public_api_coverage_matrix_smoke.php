<?php

declare(strict_types=1);

/**
 * P112Q3C static smoke test.
 *
 * Public CLI tool.
 * Role:
 *   Validate the public API coverage matrix generator contract before running
 *   the heavier local repository scan.
 *
 * Contract:
 *   Read only files and fail explicitly when the generator is absent or loses
 *   its critical report/status markers without assuming Windows path separators.
 */
$root = dirname(__DIR__, 2);
$script = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'coverage' . DIRECTORY_SEPARATOR . 'p112q3c_public_api_coverage_matrix.php';

if (!is_file($script)) {
    fwrite(STDERR, 'P112Q3C_SMOKE_FAILED: COVERAGE_SCRIPT_MISSING' . PHP_EOL);
    exit(1);
}

$content = file_get_contents($script);
if (!is_string($content)) {
    fwrite(STDERR, 'P112Q3C_SMOKE_FAILED: COVERAGE_SCRIPT_READ_FAILED' . PHP_EOL);
    exit(1);
}

$requiredMarkers = [
    'token_get_all',
    'P112Q3C_PUBLIC_API_COVERAGE_MATRIX_OK',
    'UNIT_CANDIDATE',
    'INTEGRATION_ONLY',
    'MISSING_TEST_REFERENCE',
    'p112q3c_public_api_coverage',
    'OPUS_P112Q3C_STRICT',
];

foreach ($requiredMarkers as $marker) {
    if (!str_contains($content, $marker)) {
        fwrite(STDERR, 'P112Q3C_SMOKE_FAILED: MARKER_MISSING: ' . $marker . PHP_EOL);
        exit(1);
    }
}

$lintOutput = [];
$exitCode = 0;
exec('php -l ' . escapeshellarg($script), $lintOutput, $exitCode);
if ($exitCode !== 0) {
    fwrite(STDERR, 'P112Q3C_SMOKE_FAILED: PHP_LINT_FAILED' . PHP_EOL . implode(PHP_EOL, $lintOutput) . PHP_EOL);
    exit(1);
}

echo 'P112Q3C_PUBLIC_API_COVERAGE_MATRIX_SMOKE_OK' . PHP_EOL;
