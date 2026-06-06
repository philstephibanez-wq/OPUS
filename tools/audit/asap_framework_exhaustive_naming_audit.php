<?php

declare(strict_types=1);

/**
 * P112Q2A2 — Exhaustive ASAP framework naming audit.
 *
 * This is audit-only.
 *
 * It fixes the P112Q2A blind spot: the first audit used a limited directory
 * mapping and a too-permissive PascalCase detector. This version inspects every
 * directory segment under framework/Asap and compares it with the proposed
 * modern namespace/directory policy.
 */

$workspaceRoot = 'H:\\ASAP';
$frameworkRoot = $workspaceRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap';
$reportRoot = 'H:\\ASAP_REF_BOOK\\var\\reports';

if (!is_dir($workspaceRoot)) {
    fwrite(STDERR, "ASAP_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($frameworkRoot)) {
    fwrite(STDERR, "ASAP_FRAMEWORK_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($reportRoot) && !mkdir($reportRoot, 0777, true) && !is_dir($reportRoot)) {
    fwrite(STDERR, "REPORT_ROOT_CREATE_FAILED\n");
    exit(1);
}

$stamp = gmdate('Ymd_His');
$outDir = $reportRoot . DIRECTORY_SEPARATOR . 'asap_exhaustive_naming_audit_' . $stamp;

if (!mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "REPORT_DIR_CREATE_FAILED\n");
    exit(1);
}

/**
 * Proposed target policy.
 *
 * This is a plan, not a rename operation.
 */
$targetMap = [
    'ACL' => 'Acl',
    'ACTION' => 'Action',
    'BDD' => 'Database',
    'CACHE' => 'Cache',
    'CONTROLLER' => 'Controller',
    'COOKIE' => 'Cookie',
    'CSS' => 'Css',
    'DATE' => 'Date',
    'DIRECTORY' => 'Directory',
    'EVENT' => 'Event',
    'FILE' => 'File',
    'FSM' => 'Fsm',
    'FTP' => 'Ftp',
    'HELPER' => 'Helper',
    'I18N' => 'I18n',
    'JS' => 'Javascript',
    'JSON' => 'Json',
    'LANGUAGE' => 'Language',
    'LINK' => 'Link',
    'LOG' => 'Log',
    'MAIL' => 'Mail',
    'MENU' => 'Menu',
    'MODEL' => 'Model',
    'REQUEST' => 'Request',
    'RESPONSE' => 'Response',
    'REST' => 'Rest',
    'ROUTER' => 'Router',
    'ROUTING' => 'Routing',
    'SESSION' => 'Session',
    'SITE' => 'Site',
    'SMTP' => 'Smtp',
    'TEMPLATE' => 'Template',
    'THEME' => 'Theme',
    'URL' => 'Url',
    'VIEW' => 'View',
    'XML' => 'Xml',
];

function normalizedPath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function relativePath(string $root, string $path): string
{
    $root = rtrim(normalizedPath($root), '/') . '/';
    $path = normalizedPath($path);

    if (str_starts_with($path, $root)) {
        return substr($path, strlen($root));
    }

    return $path;
}

function isLegacyUppercaseSegment(string $segment): bool
{
    if (!preg_match('/[A-Z]/', $segment)) {
        return false;
    }

    return strtoupper($segment) === $segment && preg_match('/[A-Z]{2,}/', $segment) === 1;
}

function defaultProposedSegment(string $segment): string
{
    $lower = strtolower($segment);

    return match ($lower) {
        'i18n' => 'I18n',
        'js' => 'Javascript',
        'json' => 'Json',
        'url' => 'Url',
        'xml' => 'Xml',
        'css' => 'Css',
        'ftp' => 'Ftp',
        'smtp' => 'Smtp',
        'rest' => 'Rest',
        'acl' => 'Acl',
        'fsm' => 'Fsm',
        'bdd' => 'Database',
        default => ucfirst($lower),
    };
}

function phpNamespacesInDirectory(string $directory): array
{
    $namespaces = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile() || strtolower($item->getExtension()) !== 'php') {
            continue;
        }

        $content = @file_get_contents($item->getPathname());

        if ($content === false) {
            continue;
        }

        if (preg_match_all('/^\s*namespace\s+([^;]+);/m', $content, $matches)) {
            foreach ($matches[1] as $namespace) {
                $namespaces[] = trim($namespace);
            }
        }
    }

    $namespaces = array_values(array_unique($namespaces));
    sort($namespaces, SORT_STRING);

    return $namespaces;
}

function secondNamespaceSegment(string $namespace): string
{
    $parts = explode('\\', $namespace);

    return $parts[1] ?? '';
}

function classifyDirectory(string $segment, string $proposed, array $namespaces): string
{
    if ($segment === $proposed) {
        return 'ALREADY_TARGET';
    }

    if ($segment === 'BDD') {
        return 'RISKY_ENGLISH_DOMAIN_RENAME';
    }

    if ($namespaces === []) {
        return 'SAFE_EMPTY_OR_DOC_DIRECTORY_CASE_RENAME';
    }

    $secondSegments = array_values(array_unique(array_map('secondNamespaceSegment', $namespaces)));
    sort($secondSegments, SORT_STRING);

    if (count($secondSegments) > 1) {
        return 'RISKY_MIXED_NAMESPACE_SEGMENTS';
    }

    if (($secondSegments[0] ?? '') === $proposed) {
        return 'SAFE_DIRECTORY_CASE_ONLY';
    }

    return 'RISKY_NAMESPACE_AND_DIRECTORY_RENAME';
}

function csvWrite(string $file, array $headers, array $rows): void
{
    $fp = fopen($file, 'wb');

    if ($fp === false) {
        throw new RuntimeException('CSV_OPEN_FAILED: ' . $file);
    }

    fputcsv($fp, $headers);

    foreach ($rows as $row) {
        $line = [];

        foreach ($headers as $header) {
            $line[] = $row[$header] ?? '';
        }

        fputcsv($fp, $line);
    }

    fclose($fp);
}

$findings = [];
$namespaceFindings = [];
$totalDirectories = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($frameworkRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if (!$item->isDir()) {
        continue;
    }

    $totalDirectories++;
    $path = $item->getPathname();
    $relative = relativePath($frameworkRoot, $path);
    $segment = basename($path);

    $isLegacyUpper = isLegacyUppercaseSegment($segment);
    $proposed = $targetMap[$segment] ?? ($isLegacyUpper ? defaultProposedSegment($segment) : $segment);

    if ($segment === $proposed && !$isLegacyUpper) {
        continue;
    }

    $namespaces = phpNamespacesInDirectory($path);
    $secondSegments = array_values(array_unique(array_filter(array_map('secondNamespaceSegment', $namespaces))));
    sort($secondSegments, SORT_STRING);

    $classification = classifyDirectory($segment, $proposed, $namespaces);

    $findings[] = [
        'relative_path' => $relative,
        'current_segment' => $segment,
        'proposed_segment' => $proposed,
        'classification' => $classification,
        'php_namespaces' => implode(' | ', $namespaces),
        'namespace_segments' => implode(' | ', $secondSegments),
    ];

    foreach ($namespaces as $namespace) {
        $namespaceFindings[] = [
            'relative_path' => $relative,
            'current_segment' => $segment,
            'proposed_segment' => $proposed,
            'classification' => $classification,
            'namespace' => $namespace,
            'namespace_segment' => secondNamespaceSegment($namespace),
        ];
    }
}

usort($findings, static fn(array $a, array $b): int => strcmp($a['relative_path'], $b['relative_path']));

$classificationCounts = [];

foreach ($findings as $finding) {
    $classificationCounts[$finding['classification']] = ($classificationCounts[$finding['classification']] ?? 0) + 1;
}

ksort($classificationCounts);

$summary = [
    'generated_at' => gmdate('c'),
    'framework_root' => normalizedPath($frameworkRoot),
    'directories_scanned' => $totalDirectories,
    'directory_findings' => count($findings),
    'namespace_rows' => count($namespaceFindings),
    'classification_counts' => $classificationCounts,
    'policy' => [
        'directory_case' => 'PascalCase aligned with PHP namespace segments',
        'language' => 'Pure English for code, docs, comments, generated messages, and Reference Book',
        'audit_only' => true,
        'no_silent_fallbacks' => true,
    ],
];

csvWrite(
    $outDir . DIRECTORY_SEPARATOR . 'asap_exhaustive_directory_naming_audit.csv',
    ['relative_path', 'current_segment', 'proposed_segment', 'classification', 'php_namespaces', 'namespace_segments'],
    $findings
);

csvWrite(
    $outDir . DIRECTORY_SEPARATOR . 'asap_exhaustive_namespace_segments_audit.csv',
    ['relative_path', 'current_segment', 'proposed_segment', 'classification', 'namespace', 'namespace_segment'],
    $namespaceFindings
);

file_put_contents(
    $outDir . DIRECTORY_SEPARATOR . 'asap_exhaustive_naming_summary.json',
    json_encode([
        'summary' => $summary,
        'directory_findings' => $findings,
        'namespace_findings' => $namespaceFindings,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

$md = [];
$md[] = '# P112Q2A2 — Exhaustive ASAP Naming Audit';
$md[] = '';
$md[] = 'Generated at: `' . $summary['generated_at'] . '`';
$md[] = '';
$md[] = '## Summary';
$md[] = '';
$md[] = '- Directories scanned: `' . $summary['directories_scanned'] . '`';
$md[] = '- Directory findings: `' . $summary['directory_findings'] . '`';
$md[] = '- Namespace rows: `' . $summary['namespace_rows'] . '`';
$md[] = '';
$md[] = '## Classification counts';
$md[] = '';

foreach ($classificationCounts as $classification => $count) {
    $md[] = '- `' . $classification . '`: `' . $count . '`';
}

$md[] = '';
$md[] = '## Directory findings';
$md[] = '';
$md[] = '| Path | Current | Proposed | Classification | Namespace segments |';
$md[] = '|---|---:|---:|---|---|';

foreach ($findings as $finding) {
    $md[] = '| `' . $finding['relative_path'] . '` | `' . $finding['current_segment'] . '` | `' . $finding['proposed_segment'] . '` | `' . $finding['classification'] . '` | `' . $finding['namespace_segments'] . '` |';
}

file_put_contents($outDir . DIRECTORY_SEPARATOR . 'asap_exhaustive_naming_audit.md', implode(PHP_EOL, $md) . PHP_EOL);

echo 'P112Q2A2_EXHAUSTIVE_NAMING_AUDIT_OK' . PHP_EOL;
echo 'REPORT_DIR=' . normalizedPath($outDir) . PHP_EOL;
echo 'DIRECTORY_FINDINGS=' . count($findings) . PHP_EOL;

foreach ($classificationCounts as $classification => $count) {
    echo 'COUNT_' . $classification . '=' . $count . PHP_EOL;
}

exit(0);
