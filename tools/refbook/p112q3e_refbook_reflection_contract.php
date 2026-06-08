<?php

declare(strict_types=1);

/**
 * P112Q3E RefBook Reflection contract tool.
 *
 * Public CLI tool.
 * Role:
 *   Generate an ASAP RefBook Reflection snapshot and validation report from the
 *   real framework sources without mutating source files.
 *
 * Contract:
 *   - Reflection is the technical source of truth;
 *   - AsapRefBookClass and AsapRefBookMethod attributes are the functional
 *     documentation source of truth;
 *   - reports are written under var/reports/p112q3e_refbook_reflection_contract;
 *   - snapshot is written under var/refbook/snapshot.latest.json;
 *   - strict mode fails explicitly while metadata is missing.
 */
$root = dirname(__DIR__, 2);
$strict = in_array('--strict', $argv, true) || getenv('ASAP_P112Q3E_STRICT') === '1';

requireRefBookCore($root);

$sourceRoot = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap';
$reportRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'p112q3e_refbook_reflection_contract';
$snapshotRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'refbook';
ensureDirectory($reportRoot);
ensureDirectory($snapshotRoot);

$scanner = new ASAP\RefBook\RefBookReflectionScanner();
$result = $scanner->scan($sourceRoot, 'ASAP');
$validator = new ASAP\RefBook\RefBookContractValidator();
$validation = $validator->validate($result);
$builder = new ASAP\RefBook\RefBookSnapshotBuilder();
$snapshot = $builder->build($result, $sourceRoot);

$timestamp = date('Ymd_His');
$base = $reportRoot . DIRECTORY_SEPARATOR . 'P112Q3E_REFBOOK_REFLECTION_CONTRACT_' . $timestamp;
$jsonPath = $base . '.json';
$mdPath = $base . '.md';
$htmlPath = $base . '.html';
$snapshotPath = $snapshotRoot . DIRECTORY_SEPARATOR . 'snapshot.latest.json';

$report = [
    'palier' => 'P112Q3E',
    'strict' => $strict,
    'snapshot_schema_version' => $snapshot['schema_version'],
    'summary' => $validation['summary'],
    'violations' => $validation['violations'],
    'snapshot_path' => $snapshotPath,
];
writeJson($jsonPath, $report);
writeJson($snapshotPath, $snapshot);
writeText($mdPath, buildMarkdown($report));
writeText($htmlPath, buildHtml($report));
writeText($reportRoot . DIRECTORY_SEPARATOR . 'latest.md', buildMarkdown($report));
writeText($reportRoot . DIRECTORY_SEPARATOR . 'latest.html', buildHtml($report));
writeJson($reportRoot . DIRECTORY_SEPARATOR . 'latest.json', $report);

$summary = $validation['summary'];
echo 'P112Q3E_REFBOOK_REFLECTION_CONTRACT_AUDIT_OK' . PHP_EOL;
echo 'Classes=' . (string) $summary['classes'] . ' PublicMethods=' . (string) $summary['public_methods'] . PHP_EOL;
echo 'ClassMetadataMissing=' . (string) $summary['class_metadata_missing'] . ' MethodMetadataMissing=' . (string) $summary['method_metadata_missing'] . PHP_EOL;
echo 'Violations=' . (string) $summary['violations'] . ' LoadErrors=' . (string) $summary['load_errors'] . PHP_EOL;
echo 'Snapshot=' . $snapshotPath . PHP_EOL;
echo 'JSON=' . $jsonPath . PHP_EOL;
echo 'MD=' . $mdPath . PHP_EOL;
echo 'HTML=' . $htmlPath . PHP_EOL;

if ($strict && $summary['violations'] > 0) {
    fwrite(STDERR, 'P112Q3E_REFBOOK_REFLECTION_CONTRACT_STRICT_FAILED: Violations=' . (string) $summary['violations'] . PHP_EOL);
    exit(2);
}

if ($strict) {
    echo 'P112Q3E_REFBOOK_REFLECTION_CONTRACT_STRICT_OK' . PHP_EOL;
}
exit(0);

function requireRefBookCore(string $root): void
{
    $files = [
        'framework/Asap/RefBook/Attribute/AsapRefBookClass.php',
        'framework/Asap/RefBook/Attribute/AsapRefBookMethod.php',
        'framework/Asap/RefBook/Contract/RefBookInspectableInterface.php',
        'framework/Asap/RefBook/Model/RefBookMethodEntry.php',
        'framework/Asap/RefBook/Model/RefBookClassEntry.php',
        'framework/Asap/RefBook/Model/RefBookScanResult.php',
        'framework/Asap/RefBook/RefBookReflectionScanner.php',
        'framework/Asap/RefBook/RefBookContractValidator.php',
        'framework/Asap/RefBook/RefBookSnapshotBuilder.php',
    ];
    foreach ($files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path)) {
            fwrite(STDERR, 'P112Q3E_REFBOOK_CORE_FILE_MISSING: ' . $path . PHP_EOL);
            exit(1);
        }
        require_once $path;
    }
}

function ensureDirectory(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        fwrite(STDERR, 'P112Q3E_DIRECTORY_CREATE_FAILED: ' . $path . PHP_EOL);
        exit(1);
    }
}

/** @param array<string,mixed> $data */
function writeJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        fwrite(STDERR, 'P112Q3E_JSON_ENCODE_FAILED: ' . $path . PHP_EOL);
        exit(1);
    }
    writeText($path, $json . PHP_EOL);
}

function writeText(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, 'P112Q3E_FILE_WRITE_FAILED: ' . $path . PHP_EOL);
        exit(1);
    }
}

/** @param array<string,mixed> $report */
function buildMarkdown(array $report): string
{
    $summary = $report['summary'];
    $lines = [
        '# P112Q3E RefBook Reflection Contract Report',
        '',
        '- Palier: P112Q3E',
        '- Strict: ' . ($report['strict'] ? 'yes' : 'no'),
        '- Snapshot schema: ' . $report['snapshot_schema_version'],
        '- Snapshot: `' . $report['snapshot_path'] . '`',
        '',
        '## Summary',
        '',
        '| Metric | Value |',
        '|---|---:|',
    ];
    foreach ($summary as $key => $value) {
        $lines[] = '| ' . $key . ' | ' . (string) $value . ' |';
    }
    $lines[] = '';
    $lines[] = '## First violations';
    $lines[] = '';
    $violations = array_slice($report['violations'], 0, 50);
    if ($violations === []) {
        $lines[] = 'No violation.';
    } else {
        foreach ($violations as $violation) {
            $lines[] = '- `' . $violation['code'] . '` ' . $violation['symbol'] . ($violation['method'] !== '' ? '::' . $violation['method'] : '') . ' — ' . $violation['message'];
        }
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

/** @param array<string,mixed> $report */
function buildHtml(array $report): string
{
    $md = buildMarkdown($report);
    $escaped = htmlspecialchars($md, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>P112Q3E RefBook Reflection Contract</title>'
        . '<style>body{font-family:Arial,sans-serif;margin:24px;line-height:1.45}pre{white-space:pre-wrap;background:#f6f8fa;padding:16px;border-radius:8px}</style>'
        . '</head><body><pre>' . $escaped . '</pre></body></html>' . PHP_EOL;
}
