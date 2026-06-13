<?php

declare(strict_types=1);

/**
 * P112Q3E RefBook Reflection contract smoke.
 *
 * Public CLI smoke.
 * Role:
 *   Verify that the RefBook Reflection baseline files exist, are syntax-valid
 *   and expose the expected stable markers before deeper recipes run.
 */
$root = dirname(__DIR__, 2);
$files = [
    'framework/Opus/RefBook/Attribute/OpusRefBookClass.php',
    'framework/Opus/RefBook/Attribute/OpusRefBookMethod.php',
    'framework/Opus/RefBook/Contract/RefBookInspectableInterface.php',
    'framework/Opus/RefBook/RefBookReflectionScanner.php',
    'framework/Opus/RefBook/RefBookContractValidator.php',
    'framework/Opus/RefBook/RefBookSnapshotBuilder.php',
    'tests/Contract/RefBookReflectionContractTest.php',
    'tools/refbook/p112q3e_refbook_reflection_contract.php',
];

foreach ($files as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        fwrite(STDERR, 'P112Q3E_SMOKE_FAILED: FILE_MISSING: ' . $path . PHP_EOL);
        exit(1);
    }
    $output = [];
    $exitCode = 0;
    exec('php -l ' . escapeshellarg($path), $output, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, 'P112Q3E_SMOKE_FAILED: PHP_LINT_FAILED: ' . $path . PHP_EOL . implode(PHP_EOL, $output) . PHP_EOL);
        exit(1);
    }
}

$scannerContent = file_get_contents($root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'RefBook' . DIRECTORY_SEPARATOR . 'RefBookReflectionScanner.php');
$toolContent = file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'refbook' . DIRECTORY_SEPARATOR . 'p112q3e_refbook_reflection_contract.php');
if (!is_string($scannerContent) || !is_string($toolContent)) {
    fwrite(STDERR, 'P112Q3E_SMOKE_FAILED: FILE_READ_FAILED' . PHP_EOL);
    exit(1);
}

$markers = [
    'never guesses signatures',
    'OpusRefBookClass',
    'OpusRefBookMethod',
    'RefBookSnapshotBuilder',
    'P112Q3E_REFBOOK_REFLECTION_CONTRACT_AUDIT_OK',
    'P112Q3E_REFBOOK_REFLECTION_CONTRACT_STRICT_FAILED',
];

$haystack = $scannerContent . PHP_EOL . $toolContent;
foreach ($markers as $marker) {
    if (!str_contains($haystack, $marker)) {
        fwrite(STDERR, 'P112Q3E_SMOKE_FAILED: MARKER_MISSING: ' . $marker . PHP_EOL);
        exit(1);
    }
}

echo 'P112Q3E_REFBOOK_REFLECTION_CONTRACT_SMOKE_OK' . PHP_EOL;
exit(0);
