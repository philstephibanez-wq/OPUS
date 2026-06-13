<?php
declare(strict_types=1);

/**
 * Opus RefBook source tag audit.
 *
 * Read-only checker for OPUS_REFBOOK blocks in framework/Opus sources.
 */

$root = $argv[1] ?? dirname(__DIR__, 2);
$framework = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
$reportRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'audit' . DIRECTORY_SEPARATOR . 'refbook_tags';

if (!is_dir($framework)) {
    fwrite(STDERR, 'OPUS_REFBOOK_AUDIT_FRAMEWORK_ROOT_NOT_FOUND=' . $framework . PHP_EOL);
    exit(1);
}

if (!is_dir($reportRoot) && !mkdir($reportRoot, 0775, true) && !is_dir($reportRoot)) {
    fwrite(STDERR, 'OPUS_REFBOOK_AUDIT_REPORT_ROOT_CREATE_FAILED=' . $reportRoot . PHP_EOL);
    exit(1);
}

$files = phpFiles($framework);
$symbols = [];
$annotated = [];
$malformed = [];

foreach ($files as $file) {
    $content = (string)file_get_contents($file);
    $symbol = symbolOf($content);

    if ($symbol !== null) {
        $symbols[] = [
            'file' => relativePath($root, $file),
            'symbol' => $symbol,
        ];
    }

    if (preg_match_all('/OPUS_REFBOOK:\s*(.*?)END_OPUS_REFBOOK/s', $content, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as $match) {
            $block = trim($match[1]);
            $parsed = parseBlock($block);

            if (!isset($parsed['domain'], $parsed['role'], $parsed['contract'])) {
                $malformed[] = [
                    'file' => relativePath($root, $file),
                    'symbol' => $symbol,
                    'missing' => missingRequired($parsed),
                ];
                continue;
            }

            $annotated[] = [
                'file' => relativePath($root, $file),
                'symbol' => $symbol,
                'domain' => $parsed['domain'],
                'role' => $parsed['role'],
                'contract_count' => is_array($parsed['contract']) ? count($parsed['contract']) : 1,
                'examples' => $parsed['examples'] ?? [],
                'diagrams' => $parsed['diagrams'] ?? [],
            ];
        }
    }
}

$report = [
    'schema' => 'OPUS_REFBOOK_SOURCE_TAG_AUDIT_V1',
    'generated_at' => date(DATE_ATOM),
    'symbol_count' => count($symbols),
    'annotated_count' => count($annotated),
    'malformed_count' => count($malformed),
    'annotated' => $annotated,
    'malformed' => $malformed,
    'missing_annotation_count' => count($symbols) - count($annotated),
];

file_put_contents($reportRoot . DIRECTORY_SEPARATOR . 'refbook_tag_audit.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
file_put_contents($reportRoot . DIRECTORY_SEPARATOR . 'refbook_tag_audit.md', markdownReport($report));

echo 'OPUS_REFBOOK_TAG_AUDIT_SYMBOLS=' . count($symbols) . PHP_EOL;
echo 'OPUS_REFBOOK_TAG_AUDIT_ANNOTATED=' . count($annotated) . PHP_EOL;
echo 'OPUS_REFBOOK_TAG_AUDIT_MALFORMED=' . count($malformed) . PHP_EOL;
echo 'OPUS_REFBOOK_TAG_AUDIT_REPORT=' . $reportRoot . PHP_EOL;

if (count($malformed) > 0) {
    exit(1);
}

exit(0);

/** @return list<string> */
function phpFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $info) {
        if ($info instanceof SplFileInfo && $info->isFile() && strtolower($info->getExtension()) === 'php') {
            $files[] = $info->getPathname();
        }
    }
    sort($files);
    return $files;
}

function symbolOf(string $content): ?string
{
    if (preg_match('/^\s*namespace\s+([^;{]+)[;{]/m', $content, $ns) !== 1) {
        return null;
    }

    if (preg_match('/^\s*(?:abstract\s+|final\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $content, $name) !== 1) {
        return null;
    }

    return trim($ns[1]) . '\\' . $name[1];
}

function relativePath(string $root, string $file): string
{
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/';
    $file = str_replace('\\', '/', realpath($file) ?: $file);
    return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
}

/** @return array<string,mixed> */
function parseBlock(string $block): array
{
    $result = [];
    $currentListKey = null;

    foreach (preg_split('/\R/', $block) ?: [] as $line) {
        $line = preg_replace('/^\s*\*\s?/', '', $line) ?? $line;
        $line = rtrim($line);

        if ($line === '') {
            continue;
        }

        if (preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*):\s*(.*?)\s*$/', $line, $m) === 1) {
            $key = $m[1];
            $value = $m[2];

            if ($value === '') {
                $result[$key] = [];
                $currentListKey = $key;
            } else {
                $result[$key] = $value;
                $currentListKey = null;
            }

            continue;
        }

        if ($currentListKey !== null && preg_match('/^\s*-\s+(.+?)\s*$/', $line, $m) === 1) {
            $result[$currentListKey][] = $m[1];
            continue;
        }

        $result['_unparsed'][] = $line;
    }

    return $result;
}

/** @param array<string,mixed> $parsed @return list<string> */
function missingRequired(array $parsed): array
{
    $missing = [];
    foreach (['domain', 'role', 'contract'] as $key) {
        if (!array_key_exists($key, $parsed)) {
            $missing[] = $key;
        }
    }
    return $missing;
}

/** @param array<string,mixed> $report */
function markdownReport(array $report): string
{
    $lines = [
        '# Opus RefBook Source Tag Audit',
        '',
        '- symbols: `' . $report['symbol_count'] . '`',
        '- annotated: `' . $report['annotated_count'] . '`',
        '- malformed: `' . $report['malformed_count'] . '`',
        '- missing_annotation_count: `' . $report['missing_annotation_count'] . '`',
        '',
        '## Annotated symbols',
        '',
    ];

    foreach ($report['annotated'] as $item) {
        $lines[] = '- `' . ($item['symbol'] ?? 'unknown') . '` — `' . $item['domain'] . '`';
    }

    if ($report['annotated'] === []) {
        $lines[] = '_No OPUS_REFBOOK source tags found yet._';
    }

    $lines[] = '';
    $lines[] = '## Malformed blocks';
    $lines[] = '';

    foreach ($report['malformed'] as $item) {
        $lines[] = '- `' . ($item['symbol'] ?? $item['file']) . '` missing: `' . implode(', ', $item['missing']) . '`';
    }

    if ($report['malformed'] === []) {
        $lines[] = '_No malformed blocks._';
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}