<?php

declare(strict_types=1);

/**
 * P112Q2F â€” Opus Root and Orphan Framework Audit.
 *
 * Audit-only.
 *
 * It inspects:
 * - direct PHP files under framework/Opus;
 * - direct framework directories with no PHP classes;
 * - empty/quasi-empty directories;
 * - semantic duplicate candidates such as Render vs Renderer;
 * - candidate cleanup plan for P112Q2G.
 *
 * No file is moved, renamed, deleted, or edited by this tool.
 */

$asapRoot = 'H:\\ASAP';
$refBookRoot = 'H:\\OPUS_REF_BOOK';
$frameworkRoot = $asapRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
$reportRoot = $refBookRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'reports';

if (!is_dir($asapRoot)) {
    fwrite(STDERR, "OPUS_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($refBookRoot)) {
    fwrite(STDERR, "OPUS_REF_BOOK_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($frameworkRoot)) {
    fwrite(STDERR, "OPUS_FRAMEWORK_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($reportRoot) && !mkdir($reportRoot, 0777, true) && !is_dir($reportRoot)) {
    fwrite(STDERR, "REPORT_ROOT_CREATE_FAILED\n");
    exit(1);
}

$stamp = gmdate('Ymd_His');
$outDir = $reportRoot . DIRECTORY_SEPARATOR . 'opus_root_orphan_framework_audit_' . $stamp;

if (!mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "REPORT_DIR_CREATE_FAILED\n");
    exit(1);
}

$rootFilePlan = [
    'Acl.php' => ['PUBLIC_FACADE_REVIEW', 'Acl/Acl.php', 'Opus\\Acl\\Acl', 'Root facade candidate. Review against the Acl domain before moving.'],
    'Bootstrap.php' => ['CORE_REVIEW', 'Core/Bootstrap.php', 'Opus\\Core\\Bootstrap', 'Core bootstrap candidate.'],
    'ConfigLoader.php' => ['CONFIG_REVIEW', 'Config/ConfigLoader.php', 'Opus\\Config\\ConfigLoader', 'Configuration loader candidate.'],
    'Configuration.php' => ['CONFIG_REVIEW', 'Config/Configuration.php', 'Opus\\Config\\Configuration', 'Configuration object candidate.'],
    'Debug.php' => ['DEBUG_REVIEW', 'Debug/Debug.php', 'Opus\\Debug\\Debug', 'Debug domain candidate.'],
    'Exception.php' => ['EXCEPTION_REVIEW', 'Exception/Exception.php', 'Opus\\Exception\\Exception', 'Base exception candidate.'],
    'Fsm.php' => ['PUBLIC_FACADE_REVIEW', 'Fsm/Fsm.php', 'Opus\\Fsm\\Fsm', 'Root facade candidate. Review against the Fsm domain before moving.'],
    'Kernel.php' => ['CORE_REVIEW', 'Core/Kernel.php', 'Opus\\Core\\Kernel', 'Core kernel candidate.'],
    'Package.php' => ['PACKAGE_REVIEW', 'Package/Package.php', 'Opus\\Package\\Package', 'Package domain candidate.'],
    'PackageRepository.php' => ['PACKAGE_REVIEW', 'Package/PackageRepository.php', 'Opus\\Package\\PackageRepository', 'Package repository candidate.'],
    'Response.php' => ['PUBLIC_FACADE_REVIEW', 'Response/ResponseFacade.php', 'Opus\\Response\\ResponseFacade', 'Root response facade candidate. Must be reconciled with Opus\\Response\\Response.'],
    'SimpleXMLElementExtended.class.php' => ['LEGACY_COMPAT_REVIEW', 'Compatibility/LegacySimpleXMLElementExtended.php', 'Opus\\Compatibility\\LegacySimpleXMLElementExtended', 'Legacy .class.php file. Prefer removal or explicit compatibility domain.'],
    'SimpleXMLElementExtended.php' => ['COMPATIBILITY_REVIEW', 'Compatibility/SimpleXMLElementExtended.php', 'Opus\\Compatibility\\SimpleXMLElementExtended', 'Compatibility helper candidate.'],
    'Singleton.class.php' => ['LEGACY_COMPAT_REVIEW', 'Compatibility/LegacySingleton.php', 'Opus\\Compatibility\\LegacySingleton', 'Legacy .class.php file. Prefer removal or explicit compatibility domain.'],
    'Singleton.php' => ['COMPATIBILITY_REVIEW', 'Compatibility/Singleton.php', 'Opus\\Compatibility\\Singleton', 'Compatibility helper candidate.'],
    'Support.php' => ['SUPPORT_REVIEW', 'Support/Support.php', 'Opus\\Support\\Support', 'Support domain candidate.'],
    'Validator.php' => ['VALIDATION_REVIEW', 'Validation/Validator.php', 'Opus\\Validation\\Validator', 'Validation domain candidate.'],
    'View.php' => ['PUBLIC_FACADE_REVIEW', 'View/View.php', 'Opus\\View\\View', 'Root facade candidate. Must be reconciled with Opus\\View\\Html.'],
];

$orphanDirectoryPlan = [
    'Render' => ['REMOVE_OR_MERGE_REVIEW', 'Renderer', 'Render is ambiguous and conflicts semantically with Renderer. Remove if empty, or move useful content to Renderer.'],
    'resources' => ['RESOURCE_POLICY_REVIEW', 'Resources', 'Lowercase resource directory. Decide whether it is framework resource storage or should move out of framework/Opus.'],
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

function phpFileInfo(string $path): array
{
    $content = file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException('PHP_FILE_READ_FAILED: ' . $path);
    }

    $namespace = '';
    $symbols = [];

    if (preg_match('/^\s*namespace\s+([^;]+);/m', $content, $matches)) {
        $namespace = trim($matches[1]);
    }

    if (preg_match_all('/^\s*(?:abstract\s+|final\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $content, $matches)) {
        $symbols = $matches[1];
    }

    $symbols = array_values(array_unique($symbols));

    return [
        'namespace' => $namespace,
        'symbols' => $symbols,
        'content' => $content,
        'line_count' => (string) (substr_count($content, "\n") + 1),
    ];
}

function directoryStats(string $path): array
{
    $totalFiles = 0;
    $phpFiles = 0;
    $gitkeepFiles = 0;
    $namespaces = [];
    $symbols = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $totalFiles++;
        $filePath = $item->getPathname();
        $fileName = basename($filePath);

        if ($fileName === '.gitkeep') {
            $gitkeepFiles++;
        }

        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'php') {
            continue;
        }

        $phpFiles++;
        $content = @file_get_contents($filePath);

        if ($content === false) {
            continue;
        }

        if (preg_match_all('/^\s*namespace\s+([^;]+);/m', $content, $matches)) {
            foreach ($matches[1] as $namespace) {
                $namespaces[] = trim($namespace);
            }
        }

        if (preg_match_all('/^\s*(?:abstract\s+|final\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $content, $matches)) {
            foreach ($matches[1] as $symbol) {
                $symbols[] = trim($symbol);
            }
        }
    }

    $namespaces = array_values(array_unique($namespaces));
    $symbols = array_values(array_unique($symbols));
    sort($namespaces, SORT_STRING);
    sort($symbols, SORT_STRING);

    return [
        'total_files' => $totalFiles,
        'php_files' => $phpFiles,
        'gitkeep_files' => $gitkeepFiles,
        'namespaces' => $namespaces,
        'symbols' => $symbols,
    ];
}

function shouldSkipSearchPath(string $path): bool
{
    $normalized = normalizedPath($path);

    return str_contains($normalized, '/.git/')
        || str_contains($normalized, '/vendor/')
        || str_contains($normalized, '/var/cache/')
        || str_contains($normalized, '/var/reports/')
        || str_contains($normalized, '/node_modules/')
        || str_contains(strtolower($normalized), '/tools/audit/opus_root_orphan_framework_audit.php')
        || str_contains($normalized, '/DOC/P112Q2F_')
        || str_contains($normalized, '/content/markdown/root-and-orphan-framework-audit.md');
}

function collectUsageRows(array $roots, string $rootName, array $tokens): array
{
    $tokens = array_values(array_unique(array_filter($tokens)));
    $allowedExtensions = [
        'php' => true,
        'json' => true,
        'xml' => true,
        'yml' => true,
        'yaml' => true,
        'cmd' => true,
        'ps1' => true,
        'html' => true,
        'twig' => true,
        'ini' => true,
        'md' => true,
    ];

    $rows = [];

    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $path = $item->getPathname();

            if (shouldSkipSearchPath($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (!isset($allowedExtensions[$extension])) {
                continue;
            }

            $content = @file_get_contents($path);

            if ($content === false) {
                continue;
            }

            foreach ($tokens as $token) {
                $count = substr_count($content, $token);

                if ($count <= 0) {
                    continue;
                }

                $rows[] = [
                    'subject' => $rootName,
                    'token' => $token,
                    'usage_file' => relativePath($root, $path),
                    'usage_root' => normalizedPath($root),
                    'count' => (string) $count,
                ];
            }
        }
    }

    return $rows;
}

$rootRows = [];
$directoryRows = [];
$usageRows = [];

$entries = scandir($frameworkRoot);

if ($entries === false) {
    fwrite(STDERR, "FRAMEWORK_ROOT_SCAN_FAILED\n");
    exit(1);
}

foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }

    $path = $frameworkRoot . DIRECTORY_SEPARATOR . $entry;

    if (is_file($path) && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'php') {
        $info = phpFileInfo($path);
        $plan = $rootFilePlan[$entry] ?? ['UNKNOWN_ROOT_FILE', '', '', 'No target plan yet. Manual review required.'];
        $fqcnList = [];

        foreach ($info['symbols'] as $symbol) {
            $fqcnList[] = $info['namespace'] !== '' ? $info['namespace'] . '\\' . $symbol : $symbol;
        }

        $rootRows[] = [
            'file' => $entry,
            'relative_path' => 'framework/Opus/' . $entry,
            'namespace' => $info['namespace'],
            'symbols' => implode(' | ', $info['symbols']),
            'fqcn' => implode(' | ', $fqcnList),
            'line_count' => $info['line_count'],
            'classification' => $plan[0],
            'proposed_path' => $plan[1],
            'proposed_fqcn' => $plan[2],
            'note' => $plan[3],
        ];

        $tokens = ['framework/Opus/' . $entry, 'framework\\Opus\\' . $entry, 'framework\\\\Opus\\\\' . $entry];

        foreach ($info['symbols'] as $symbol) {
            $tokens[] = $symbol;

            if ($info['namespace'] !== '') {
                $tokens[] = $info['namespace'] . '\\' . $symbol;
                $tokens[] = str_replace('\\', '\\\\', $info['namespace'] . '\\' . $symbol);
            }
        }

        $usageRows = array_merge($usageRows, collectUsageRows([$asapRoot, $refBookRoot], $entry, $tokens));
        continue;
    }

    if (is_dir($path)) {
        $stats = directoryStats($path);
        $plan = $orphanDirectoryPlan[$entry] ?? ['', '', ''];
        $classification = '';

        if ($stats['php_files'] === 0 && $stats['total_files'] === 0) {
            $classification = 'EMPTY_DIRECTORY_REVIEW';
        } elseif ($stats['php_files'] === 0 && $stats['total_files'] === $stats['gitkeep_files']) {
            $classification = 'GITKEEP_ONLY_DIRECTORY_REVIEW';
        } elseif ($stats['php_files'] === 0) {
            $classification = 'NO_PHP_DIRECTORY_REVIEW';
        } elseif ($entry === 'Render' && is_dir($frameworkRoot . DIRECTORY_SEPARATOR . 'Renderer')) {
            $classification = 'SEMANTIC_DUPLICATE_REVIEW';
        } else {
            $classification = 'DOMAIN_DIRECTORY_PRESENT';
        }

        if (($plan[0] ?? '') !== '') {
            $classification = $plan[0];
        }

        $directoryRows[] = [
            'directory' => $entry,
            'relative_path' => 'framework/Opus/' . $entry,
            'total_files' => (string) $stats['total_files'],
            'php_files' => (string) $stats['php_files'],
            'gitkeep_files' => (string) $stats['gitkeep_files'],
            'namespaces' => implode(' | ', $stats['namespaces']),
            'symbols' => implode(' | ', $stats['symbols']),
            'classification' => $classification,
            'proposed_target' => $plan[1] ?? '',
            'note' => $plan[2] ?? '',
        ];

        if ($classification !== 'DOMAIN_DIRECTORY_PRESENT') {
            $tokens = [
                'framework/Opus/' . $entry,
                'framework\\Opus\\' . $entry,
                'framework\\\\Opus\\\\' . $entry,
                'ASAP/' . $entry,
            ];

            $usageRows = array_merge($usageRows, collectUsageRows([$asapRoot, $refBookRoot], $entry, $tokens));
        }
    }
}

usort($rootRows, static fn(array $a, array $b): int => strcmp($a['file'], $b['file']));
usort($directoryRows, static fn(array $a, array $b): int => strcmp($a['directory'], $b['directory']));
usort($usageRows, static fn(array $a, array $b): int => [$a['subject'], $a['usage_root'], $a['usage_file'], $a['token']] <=> [$b['subject'], $b['usage_root'], $b['usage_file'], $b['token']]);

$rootClassificationCounts = [];
$directoryClassificationCounts = [];

foreach ($rootRows as $row) {
    $rootClassificationCounts[$row['classification']] = ($rootClassificationCounts[$row['classification']] ?? 0) + 1;
}

foreach ($directoryRows as $row) {
    $directoryClassificationCounts[$row['classification']] = ($directoryClassificationCounts[$row['classification']] ?? 0) + 1;
}

ksort($rootClassificationCounts);
ksort($directoryClassificationCounts);

$summary = [
    'generated_at' => gmdate('c'),
    'framework_root' => normalizedPath($frameworkRoot),
    'root_php_files' => count($rootRows),
    'framework_directories' => count($directoryRows),
    'usage_rows' => count($usageRows),
    'root_classification_counts' => $rootClassificationCounts,
    'directory_classification_counts' => $directoryClassificationCounts,
    'policy' => [
        'root_php_files_allowed' => false,
        'empty_framework_directories_allowed' => false,
        'semantic_duplicates_allowed' => false,
        'audit_only' => true,
        'no_moves_in_this_step' => true,
        'no_deletes_in_this_step' => true,
        'no_silent_fallbacks' => true,
    ],
];

csvWrite(
    $outDir . DIRECTORY_SEPARATOR . 'opus_root_namespace_files.csv',
    ['file', 'relative_path', 'namespace', 'symbols', 'fqcn', 'line_count', 'classification', 'proposed_path', 'proposed_fqcn', 'note'],
    $rootRows
);

csvWrite(
    $outDir . DIRECTORY_SEPARATOR . 'opus_framework_directories.csv',
    ['directory', 'relative_path', 'total_files', 'php_files', 'gitkeep_files', 'namespaces', 'symbols', 'classification', 'proposed_target', 'note'],
    $directoryRows
);

csvWrite(
    $outDir . DIRECTORY_SEPARATOR . 'opus_root_orphan_usages.csv',
    ['subject', 'token', 'usage_file', 'usage_root', 'count'],
    $usageRows
);

file_put_contents(
    $outDir . DIRECTORY_SEPARATOR . 'opus_root_orphan_framework_audit_summary.json',
    json_encode([
        'summary' => $summary,
        'root_files' => $rootRows,
        'framework_directories' => $directoryRows,
        'usage_rows' => $usageRows,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

$md = [];
$md[] = '# P112Q2F â€” Opus Root and Orphan Framework Audit';
$md[] = '';
$md[] = 'Generated at: `' . $summary['generated_at'] . '`';
$md[] = '';
$md[] = '## Summary';
$md[] = '';
$md[] = '- Root PHP files: `' . count($rootRows) . '`';
$md[] = '- Framework directories: `' . count($directoryRows) . '`';
$md[] = '- Usage rows: `' . count($usageRows) . '`';
$md[] = '';
$md[] = '## Root file policy';
$md[] = '';
$md[] = '`framework/Opus/*.php` is not accepted as a final framework layout.';
$md[] = '';
$md[] = 'Every PHP framework class must live in a clear domain directory.';
$md[] = '';
$md[] = '## Directory policy';
$md[] = '';
$md[] = 'Empty decorative directories and semantic duplicates are not accepted.';
$md[] = '';
$md[] = '## Root file classification counts';
$md[] = '';

foreach ($rootClassificationCounts as $classification => $count) {
    $md[] = '- `' . $classification . '`: `' . $count . '`';
}

$md[] = '';
$md[] = '## Directory classification counts';
$md[] = '';

foreach ($directoryClassificationCounts as $classification => $count) {
    $md[] = '- `' . $classification . '`: `' . $count . '`';
}

$md[] = '';
$md[] = '## Root PHP files';
$md[] = '';
$md[] = '| File | FQCN | Classification | Proposed path | Proposed FQCN |';
$md[] = '|---|---|---|---|---|';

foreach ($rootRows as $row) {
    $md[] = '| `' . $row['file'] . '` | `' . $row['fqcn'] . '` | `' . $row['classification'] . '` | `' . $row['proposed_path'] . '` | `' . $row['proposed_fqcn'] . '` |';
}

$md[] = '';
$md[] = '## Non-standard / reviewed directories';
$md[] = '';
$md[] = '| Directory | Files | PHP | Classification | Proposed target | Note |';
$md[] = '|---|---:|---:|---|---|---|';

foreach ($directoryRows as $row) {
    if ($row['classification'] === 'DOMAIN_DIRECTORY_PRESENT') {
        continue;
    }

    $md[] = '| `' . $row['directory'] . '` | `' . $row['total_files'] . '` | `' . $row['php_files'] . '` | `' . $row['classification'] . '` | `' . $row['proposed_target'] . '` | ' . $row['note'] . ' |';
}

file_put_contents($outDir . DIRECTORY_SEPARATOR . 'opus_root_orphan_framework_audit.md', implode(PHP_EOL, $md) . PHP_EOL);

echo 'P112Q2F_ROOT_ORPHAN_FRAMEWORK_AUDIT_OK' . PHP_EOL;
echo 'REPORT_DIR=' . normalizedPath($outDir) . PHP_EOL;
echo 'ROOT_PHP_FILES=' . count($rootRows) . PHP_EOL;
echo 'FRAMEWORK_DIRECTORIES=' . count($directoryRows) . PHP_EOL;
echo 'USAGE_ROWS=' . count($usageRows) . PHP_EOL;

foreach ($rootClassificationCounts as $classification => $count) {
    echo 'ROOT_COUNT_' . $classification . '=' . $count . PHP_EOL;
}

foreach ($directoryClassificationCounts as $classification => $count) {
    echo 'DIR_COUNT_' . $classification . '=' . $count . PHP_EOL;
}

exit(0);
