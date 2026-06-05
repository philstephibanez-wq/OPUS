<?php

declare(strict_types=1);

/**
 * P112Q2A — ASAP Framework Naming and English Policy Audit.
 *
 * This is an audit-only tool.
 *
 * It scans the current ASAP framework and reports:
 * - directory names not aligned with the target PascalCase namespace policy;
 * - legacy uppercase directory segments;
 * - French/franglais tokens in PHP/MD/XML/JSON/TXT files;
 * - proposed normalized English names where the mapping is unambiguous.
 *
 * The tool does not rename, edit, or delete framework files.
 */

$workspaceRoot = 'H:\\ASAP';
$frameworkRoot = $workspaceRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'ASAP';
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
$outDir = $reportRoot . DIRECTORY_SEPARATOR . 'asap_naming_english_policy_' . $stamp;

if (!mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "REPORT_DIR_CREATE_FAILED\n");
    exit(1);
}

$directoryPolicyMap = [
    'ACL' => ['Acl', 'SAFE_CASE_ONLY'],
    'BDD' => ['Database', 'RISKY_SEMANTIC_ENGLISH_RENAME'],
    'FSM' => ['Fsm', 'SAFE_CASE_ONLY'],
    'I18N' => ['I18n', 'SAFE_CASE_ONLY'],
    'LINK' => ['Link', 'SAFE_CASE_ONLY'],
    'MAIL' => ['Mail', 'SAFE_CASE_ONLY'],
    'MENU' => ['Menu', 'SAFE_CASE_ONLY'],
    'MODEL' => ['Model', 'SAFE_CASE_ONLY'],
    'ROUTING' => ['Routing', 'SAFE_CASE_ONLY'],
    'TEMPLATE' => ['Template', 'SAFE_CASE_ONLY'],
    'THEME' => ['Theme', 'ALREADY_TARGET_OR_SAFE_CASE_ONLY'],
    'VIEW' => ['View', 'SAFE_CASE_ONLY'],
];

$frenchTokenMap = [
    'aval[a-z_]*' => ['available', 'LEGACY_TYPO_OR_FRANGLAIS'],
    'bdd' => ['database', 'FRENCH_TECHNICAL_ABBREVIATION'],
    'chemin' => ['path', 'FRENCH_TOKEN'],
    'dossier' => ['directory', 'FRENCH_TOKEN'],
    'fichier' => ['file', 'FRENCH_TOKEN'],
    'langue' => ['language_or_locale', 'FRENCH_TOKEN_REVIEW_CONTEXT'],
    'libell[eé]' => ['label', 'FRENCH_TOKEN'],
    'mot[_-]?de[_-]?passe' => ['password', 'FRENCH_TOKEN'],
    'param[eè]tre' => ['parameter_or_setting', 'FRENCH_TOKEN_REVIEW_CONTEXT'],
    'profil' => ['profile', 'FRANGLAIS_TOKEN'],
    'requ[eê]te' => ['request_or_query', 'FRENCH_TOKEN_REVIEW_CONTEXT'],
    'rubrique' => ['section', 'FRENCH_TOKEN'],
    'traitement' => ['processing', 'FRENCH_TOKEN_REVIEW_CONTEXT'],
    'utilisateur' => ['user', 'FRENCH_TOKEN'],
    'connexion' => ['connection_or_login', 'FRENCH_TOKEN_REVIEW_CONTEXT'],
    'deconnexion|déconnexion' => ['logout_or_disconnection', 'FRENCH_TOKEN_REVIEW_CONTEXT'],
    'droit[s]?' => ['permissions_or_rights', 'FRENCH_TOKEN_REVIEW_CONTEXT'],
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

function isPascalCaseSegment(string $segment): bool
{
    return (bool) preg_match('/^[A-Z][A-Za-z0-9]*$/', $segment);
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

$directoryFindings = [];
$allDirectories = [];

$dirIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($frameworkRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($dirIterator as $item) {
    if (!$item->isDir()) {
        continue;
    }

    $path = $item->getPathname();
    $relative = relativePath($frameworkRoot, $path);
    $segment = basename($path);
    $allDirectories[] = $relative;

    $target = $directoryPolicyMap[$segment][0] ?? null;
    $classification = $directoryPolicyMap[$segment][1] ?? null;

    if ($target !== null && $segment !== $target) {
        $directoryFindings[] = [
            'relative_path' => $relative,
            'current_segment' => $segment,
            'proposed_segment' => $target,
            'classification' => $classification,
            'reason' => 'Legacy directory segment does not match PascalCase English policy.',
        ];

        continue;
    }

    if (!isPascalCaseSegment($segment)) {
        $directoryFindings[] = [
            'relative_path' => $relative,
            'current_segment' => $segment,
            'proposed_segment' => '',
            'classification' => 'REVIEW_NON_PASCALCASE',
            'reason' => 'Directory segment is not PascalCase and has no predefined mapping.',
        ];
    }
}

sort($allDirectories, SORT_STRING);

$fileExtensions = ['php' => true, 'md' => true, 'xml' => true, 'json' => true, 'txt' => true];
$languageFindings = [];
$maxHitsPerTokenPerFile = 5;

$fileIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($workspaceRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($fileIterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relative = relativePath($workspaceRoot, $path);

    if (str_starts_with(normalizedPath($relative), '.git/')) {
        continue;
    }

    if (str_contains(normalizedPath($relative), '/vendor/')) {
        continue;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if (!isset($fileExtensions[$extension])) {
        continue;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        continue;
    }

    $hitsPerToken = [];

    foreach ($lines as $lineNumber => $line) {
        foreach ($frenchTokenMap as $pattern => [$suggestion, $classification]) {
            $regex = '/\b(' . $pattern . ')\b/iu';

            if (!preg_match_all($regex, $line, $matches)) {
                continue;
            }

            $hitsPerToken[$pattern] = $hitsPerToken[$pattern] ?? 0;

            if ($hitsPerToken[$pattern] >= $maxHitsPerTokenPerFile) {
                continue;
            }

            foreach ($matches[1] as $match) {
                if ($hitsPerToken[$pattern] >= $maxHitsPerTokenPerFile) {
                    break;
                }

                $languageFindings[] = [
                    'relative_path' => $relative,
                    'line' => (string) ($lineNumber + 1),
                    'token' => $match,
                    'suggestion' => $suggestion,
                    'classification' => $classification,
                    'excerpt' => trim(mb_substr($line, 0, 240)),
                ];

                $hitsPerToken[$pattern]++;
            }
        }
    }
}

$summary = [
    'generated_at' => gmdate('c'),
    'framework_root' => normalizedPath($frameworkRoot),
    'directories_scanned' => count($allDirectories),
    'directory_findings' => count($directoryFindings),
    'language_findings' => count($languageFindings),
    'policy' => [
        'directory_case' => 'PascalCase aligned with PHP namespace segments',
        'language' => 'Pure English for code, docs, comments, generated messages, and Reference Book',
        'no_runtime_renames_in_this_step' => true,
        'no_silent_fallbacks' => true,
    ],
];

csvWrite(
    $outDir . DIRECTORY_SEPARATOR . 'asap_directory_case_policy_audit.csv',
    ['relative_path', 'current_segment', 'proposed_segment', 'classification', 'reason'],
    $directoryFindings
);

csvWrite(
    $outDir . DIRECTORY_SEPARATOR . 'asap_english_policy_audit.csv',
    ['relative_path', 'line', 'token', 'suggestion', 'classification', 'excerpt'],
    $languageFindings
);

file_put_contents(
    $outDir . DIRECTORY_SEPARATOR . 'asap_naming_english_policy_summary.json',
    json_encode([
        'summary' => $summary,
        'directory_findings' => $directoryFindings,
        'language_findings' => $languageFindings,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

$md = [];
$md[] = '# P112Q2A — ASAP Naming and English Policy Audit';
$md[] = '';
$md[] = 'Generated at: `' . $summary['generated_at'] . '`';
$md[] = '';
$md[] = '## Summary';
$md[] = '';
$md[] = '- Directories scanned: `' . $summary['directories_scanned'] . '`';
$md[] = '- Directory findings: `' . $summary['directory_findings'] . '`';
$md[] = '- English/franglais findings: `' . $summary['language_findings'] . '`';
$md[] = '';
$md[] = '## Directory policy';
$md[] = '';
$md[] = '`framework/ASAP/<NamespaceSegment>/<ClassName>.php`';
$md[] = '';
$md[] = 'Directory segments must be PascalCase and aligned with PHP namespace segments.';
$md[] = '';
$md[] = '## Language policy';
$md[] = '';
$md[] = 'Code, comments, diagnostics, documentation, and Reference Book pages must use pure English.';
$md[] = '';
$md[] = '## Important';
$md[] = '';
$md[] = 'This audit does not rename files or directories.';
$md[] = '';
$md[] = '## Directory findings';
$md[] = '';

if ($directoryFindings === []) {
    $md[] = 'No directory naming findings.';
} else {
    $md[] = '| Current path | Current | Proposed | Classification |';
    $md[] = '|---|---:|---:|---|';

    foreach ($directoryFindings as $finding) {
        $md[] = '| `' . $finding['relative_path'] . '` | `' . $finding['current_segment'] . '` | `' . $finding['proposed_segment'] . '` | `' . $finding['classification'] . '` |';
    }
}

$md[] = '';
$md[] = '## English/franglais findings';
$md[] = '';

if ($languageFindings === []) {
    $md[] = 'No English policy findings.';
} else {
    $md[] = '| File | Line | Token | Suggestion | Classification |';
    $md[] = '|---|---:|---:|---:|---|';

    foreach (array_slice($languageFindings, 0, 200) as $finding) {
        $md[] = '| `' . $finding['relative_path'] . '` | `' . $finding['line'] . '` | `' . $finding['token'] . '` | `' . $finding['suggestion'] . '` | `' . $finding['classification'] . '` |';
    }

    if (count($languageFindings) > 200) {
        $md[] = '';
        $md[] = 'Output truncated in Markdown. Full CSV contains all findings.';
    }
}

file_put_contents($outDir . DIRECTORY_SEPARATOR . 'asap_naming_english_policy_audit.md', implode(PHP_EOL, $md) . PHP_EOL);

echo 'P112Q2A_NAMING_ENGLISH_POLICY_AUDIT_OK' . PHP_EOL;
echo 'REPORT_DIR=' . normalizedPath($outDir) . PHP_EOL;
echo 'DIRECTORY_FINDINGS=' . count($directoryFindings) . PHP_EOL;
echo 'LANGUAGE_FINDINGS=' . count($languageFindings) . PHP_EOL;

exit(0);
