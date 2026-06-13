<?php

declare(strict_types=1);

/**
 * P112Q3E2 ACL RefBook metadata audit.
 *
 * Public CLI tool.
 * Role:
 *   Generate an observable JSON/MD/HTML report proving the Opus ACL domain is
 *   fully covered by Reflection-backed RefBook metadata.
 */
$root = dirname(__DIR__, 2);
$strict = in_array('--strict', $argv, true) || getenv('OPUS_P112Q3E2_STRICT') === '1';
requireRefBookCore($root);

$aclRoot = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Acl';
$reportRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'p112q3e2_refbook_acl_metadata';
$snapshotRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'refbook';
ensureDirectory($reportRoot);
ensureDirectory($snapshotRoot);

$scanner = new ASAP\RefBook\RefBookReflectionScanner();
$result = $scanner->scan($aclRoot, 'Opus\\Acl');
$validator = new ASAP\RefBook\RefBookContractValidator();
$validation = $validator->validate($result);
$builder = new ASAP\RefBook\RefBookSnapshotBuilder();
$snapshot = $builder->build($result, $aclRoot);

$timestamp = date('Ymd_His');
$base = $reportRoot . DIRECTORY_SEPARATOR . 'P112Q3E2_REFBOOK_ACL_METADATA_' . $timestamp;
$jsonPath = $base . '.json';
$mdPath = $base . '.md';
$htmlPath = $base . '.html';
$snapshotPath = $snapshotRoot . DIRECTORY_SEPARATOR . 'snapshot.acl.latest.json';

$report = [
    'palier' => 'P112Q3E2',
    'strict' => $strict,
    'domain' => 'ACL',
    'snapshot_schema_version' => $snapshot['schema_version'],
    'summary' => $validation['summary'],
    'violations' => $validation['violations'],
    'snapshot_path' => $snapshotPath,
];
writeJson($jsonPath, $report);
writeJson($snapshotPath, $snapshot);
writeText($mdPath, buildMarkdown($report));
writeText($htmlPath, buildHtml($report));
writeJson($reportRoot . DIRECTORY_SEPARATOR . 'latest.json', $report);
writeText($reportRoot . DIRECTORY_SEPARATOR . 'latest.md', buildMarkdown($report));
writeText($reportRoot . DIRECTORY_SEPARATOR . 'latest.html', buildHtml($report));

$summary = $validation['summary'];
echo 'P112Q3E2_REFBOOK_ACL_METADATA_AUDIT_OK' . PHP_EOL;
echo 'Classes=' . (string) $summary['classes'] . ' PublicMethods=' . (string) $summary['public_methods'] . PHP_EOL;
echo 'ClassMetadataMissing=' . (string) $summary['class_metadata_missing'] . ' MethodMetadataMissing=' . (string) $summary['method_metadata_missing'] . PHP_EOL;
echo 'Violations=' . (string) $summary['violations'] . ' LoadErrors=' . (string) $summary['load_errors'] . PHP_EOL;
echo 'Snapshot=' . $snapshotPath . PHP_EOL;
echo 'JSON=' . $jsonPath . PHP_EOL;
echo 'MD=' . $mdPath . PHP_EOL;
echo 'HTML=' . $htmlPath . PHP_EOL;

if ($strict && $summary['violations'] > 0) {
    fwrite(STDERR, 'P112Q3E2_REFBOOK_ACL_METADATA_STRICT_FAILED: Violations=' . (string) $summary['violations'] . PHP_EOL);
    exit(2);
}

if ($strict) {
    echo 'P112Q3E2_REFBOOK_ACL_METADATA_STRICT_OK' . PHP_EOL;
}
exit(0);

function requireRefBookCore(string $root): void
{
    $files = [
        'framework/Opus/RefBook/Attribute/OpusRefBookClass.php',
        'framework/Opus/RefBook/Attribute/OpusRefBookMethod.php',
        'framework/Opus/RefBook/Contract/RefBookInspectableInterface.php',
        'framework/Opus/RefBook/Model/RefBookMethodEntry.php',
        'framework/Opus/RefBook/Model/RefBookClassEntry.php',
        'framework/Opus/RefBook/Model/RefBookScanResult.php',
        'framework/Opus/RefBook/RefBookReflectionScanner.php',
        'framework/Opus/RefBook/RefBookContractValidator.php',
        'framework/Opus/RefBook/RefBookSnapshotBuilder.php',
    ];
    foreach ($files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path)) {
            fwrite(STDERR, 'P112Q3E2_REFBOOK_CORE_FILE_MISSING: ' . $path . PHP_EOL);
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
        fwrite(STDERR, 'P112Q3E2_DIRECTORY_CREATE_FAILED: ' . $path . PHP_EOL);
        exit(1);
    }
}

/** @param array<string,mixed> $data */
function writeJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        fwrite(STDERR, 'P112Q3E2_JSON_ENCODE_FAILED: ' . $path . PHP_EOL);
        exit(1);
    }
    writeText($path, $json . PHP_EOL);
}

function writeText(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, 'P112Q3E2_FILE_WRITE_FAILED: ' . $path . PHP_EOL);
        exit(1);
    }
}

/** @param array<string,mixed> $report */
function buildMarkdown(array $report): string
{
    $summary = $report['summary'];
    $lines = [
        '# P112Q3E2 ACL RefBook Metadata Report',
        '',
        '- Palier: P112Q3E2',
        '- Domain: ACL',
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
    $lines[] = '## Violations';
    $lines[] = '';
    if ($report['violations'] === []) {
        $lines[] = 'No violation.';
    } else {
        foreach ($report['violations'] as $violation) {
            $lines[] = '- `' . $violation['code'] . '` ' . $violation['symbol'] . ($violation['method'] !== '' ? '::' . $violation['method'] : '') . ' — ' . $violation['message'];
        }
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

/** @param array<string,mixed> $report */
function buildHtml(array $report): string
{
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>P112Q3E2 ACL RefBook Metadata</title></head><body><pre>'
        . htmlspecialchars(buildMarkdown($report), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</pre></body></html>' . PHP_EOL;
}
