<?php
declare(strict_types=1);

/**
 * P114D3 RefBook documentation I18N missing source extractor.
 *
 * Contract:
 * - scans ASAP RefBook source metadata through the official snapshot provider;
 * - compares source documentation strings against the ASAP I18N catalog;
 * - writes candidate translation files for human review;
 * - never mutates the official catalog automatically.
 */

$root = dirname(__DIR__, 2);
$outputRoot = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'P114D3_REFBOOK_DOC_I18N';

for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--output-root' && isset($argv[$i + 1])) {
        $outputRoot = $argv[$i + 1];
        $i++;
    }
}

$vendor = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($vendor)) {
    require_once $vendor;
}

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ASAP\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

use ASAP\RefBook\Api\RefBookRestSnapshotProvider;
use ASAP\RefBook\I18n\RefBookDocumentationI18nCatalog;
use ASAP\RefBook\I18n\RefBookDocumentationLocale;

function p114d3_fail(string $message): never
{
    fwrite(STDERR, "P114D3_REFBOOK_DOC_I18N_MISSING_EXTRACTOR_FAIL\n" . $message . "\n");
    exit(1);
}

/** @return list<mixed> */
function p114d3_list(mixed $value): array
{
    return is_array($value) ? array_values($value) : [];
}

/** @return array<string,mixed> */
function p114d3_array(mixed $value): array
{
    return is_array($value) ? $value : [];
}

function p114d3_technical_literal(string $value): bool
{
    $value = trim($value);
    if ($value === '' || $value === 'public-api' || $value === 'internal' || $value === 'private') {
        return true;
    }
    if (preg_match('/^[A-Z0-9_]+$/', $value) === 1) {
        return true;
    }
    if (preg_match('/^[A-Za-z0-9_.-]+$/', $value) === 1 && !str_contains($value, ' ')) {
        return true;
    }
    if (preg_match('/^[A-Za-z0-9_\\\\]+(::[A-Za-z0-9_]+)?$/', $value) === 1) {
        return true;
    }
    if (str_starts_with($value, 'tests/') || str_starts_with($value, 'DOC/')) {
        return true;
    }
    return false;
}

/**
 * @param array<string,array<string,mixed>> $index
 * @param list<string> $languages
 * @param array<string,array<string,string>> $catalog
 * @param array<string,mixed> $occurrence
 */
function p114d3_collect(array &$index, array $languages, array $catalog, string $source, array $occurrence): void
{
    $source = trim($source);
    if ($source === '' || p114d3_technical_literal($source)) {
        return;
    }
    if (!isset($index[$source])) {
        $entry = $catalog[$source] ?? [];
        $missing = [];
        $present = [];
        foreach ($languages as $language) {
            if (isset($entry[$language]) && trim((string) $entry[$language]) !== '') {
                $present[$language] = $entry[$language];
            } else {
                $missing[] = $language;
            }
        }
        $index[$source] = [
            'source' => $source,
            'sha1' => sha1($source),
            'missing_languages' => $missing,
            'present_languages' => array_keys($present),
            'existing_translations' => $present,
            'occurrences' => [],
        ];
    }
    $index[$source]['occurrences'][] = $occurrence;
}

/**
 * @param array<string,array<string,mixed>> $index
 * @param list<string> $languages
 * @param array<string,array<string,string>> $catalog
 * @param array<string,mixed> $context
 */
function p114d3_collect_list(array &$index, array $languages, array $catalog, mixed $value, array $context): void
{
    foreach (p114d3_list($value) as $idx => $item) {
        p114d3_collect($index, $languages, $catalog, (string) $item, array_replace($context, ['item_index' => $idx]));
    }
}

/**
 * @param array<string,array<string,mixed>> $missing
 * @param list<string> $languages
 * @return array<string,array<string,string>>
 */
function p114d3_candidate_catalog(array $missing, array $languages): array
{
    $out = [];
    foreach ($missing as $source => $entry) {
        $translations = [];
        foreach ($languages as $language) {
            if (isset($entry['existing_translations'][$language])) {
                $translations[$language] = (string) $entry['existing_translations'][$language];
            } elseif ($language === 'en') {
                $translations[$language] = $source;
            } else {
                $translations[$language] = 'TODO_TRANSLATE[' . $language . ']: ' . $source;
            }
        }
        $out[$source] = $translations;
    }
    return $out;
}

/** @param array<string,array<string,string>> $candidate */
function p114d3_php_array_file(array $candidate): string
{
    return "<?php\n\ndeclare(strict_types=1);\n\n/**\n * P114D3 generated candidate catalog.\n *\n * Review manually before merging entries into ASAP\\RefBook\\I18n\\RefBookDocumentationI18nCatalog.\n */\nreturn " . var_export($candidate, true) . ";\n";
}

if (!class_exists(RefBookRestSnapshotProvider::class)) {
    p114d3_fail('missing snapshot provider: ' . RefBookRestSnapshotProvider::class);
}
if (!class_exists(RefBookDocumentationI18nCatalog::class)) {
    p114d3_fail('missing I18N catalog: ' . RefBookDocumentationI18nCatalog::class);
}

$provider = new RefBookRestSnapshotProvider($root);
$snapshot = $provider->snapshot();
$catalog = (new RefBookDocumentationI18nCatalog())->all();
$languages = RefBookDocumentationLocale::supported();

$sourceIndex = [];
$classCount = 0;
$methodCount = 0;

foreach (p114d3_list($snapshot['classes'] ?? []) as $classIndex => $class) {
    if (!is_array($class)) { continue; }
    $classCount++;
    $className = (string) ($class['name'] ?? ('class#' . (string) $classIndex));
    $classDomain = (string) (($class['metadata']['domain'] ?? '') ?: 'UNCLASSIFIED');
    $metadata = p114d3_array($class['metadata'] ?? []);

    p114d3_collect($sourceIndex, $languages, $catalog, (string) ($metadata['role'] ?? ''), [
        'domain' => $classDomain, 'symbol' => $className, 'method' => '', 'field' => 'role',
        'path' => 'classes.' . (string) $classIndex . '.metadata.role',
    ]);
    p114d3_collect($sourceIndex, $languages, $catalog, (string) ($metadata['responsibility'] ?? ''), [
        'domain' => $classDomain, 'symbol' => $className, 'method' => '', 'field' => 'responsibility',
        'path' => 'classes.' . (string) $classIndex . '.metadata.responsibility',
    ]);
    p114d3_collect_list($sourceIndex, $languages, $catalog, $metadata['contracts'] ?? [], [
        'domain' => $classDomain, 'symbol' => $className, 'method' => '', 'field' => 'contracts',
        'path' => 'classes.' . (string) $classIndex . '.metadata.contracts',
    ]);

    foreach (p114d3_list($class['methods'] ?? []) as $methodIndex => $method) {
        if (!is_array($method)) { continue; }
        $methodCount++;
        $methodName = (string) ($method['name'] ?? ('method#' . (string) $methodIndex));
        $methodMetadata = p114d3_array($method['metadata'] ?? []);
        $baseContext = [
            'domain' => $classDomain,
            'symbol' => $className,
            'method' => $methodName,
            'path' => 'classes.' . (string) $classIndex . '.methods.' . (string) $methodIndex . '.metadata',
        ];
        foreach (['role', 'behavior'] as $field) {
            p114d3_collect($sourceIndex, $languages, $catalog, (string) ($methodMetadata[$field] ?? ''), array_replace($baseContext, [
                'field' => $field,
                'path' => $baseContext['path'] . '.' . $field,
            ]));
        }
        foreach (['preconditions', 'postconditions', 'side_effects', 'errors'] as $field) {
            p114d3_collect_list($sourceIndex, $languages, $catalog, $methodMetadata[$field] ?? [], array_replace($baseContext, [
                'field' => $field,
                'path' => $baseContext['path'] . '.' . $field,
            ]));
        }
    }
}

ksort($sourceIndex);
$missing = array_filter($sourceIndex, static fn(array $entry): bool => ($entry['missing_languages'] ?? []) !== []);
uasort($missing, static function (array $a, array $b): int {
    $domainCompare = strcmp((string) (($a['occurrences'][0]['domain'] ?? '')), (string) (($b['occurrences'][0]['domain'] ?? '')));
    return $domainCompare !== 0 ? $domainCompare : strcmp((string) $a['source'], (string) $b['source']);
});

if (!is_dir($outputRoot) && !mkdir($outputRoot, 0777, true) && !is_dir($outputRoot)) {
    p114d3_fail('unable to create output root: ' . $outputRoot);
}

$reportPath = $outputRoot . DIRECTORY_SEPARATOR . 'refbook_doc_i18n_missing_source_texts.json';
$candidatePath = $outputRoot . DIRECTORY_SEPARATOR . 'refbook_doc_i18n_candidate_catalog.php';
$summaryPath = $outputRoot . DIRECTORY_SEPARATOR . 'refbook_doc_i18n_summary.txt';

$report = [
    'schema' => 'P114D3_REFBOOK_DOC_I18N_MISSING_SOURCE_TEXTS_V1',
    'generated_at' => gmdate('c'),
    'asap_root' => $root,
    'languages' => $languages,
    'class_count' => $classCount,
    'method_count' => $methodCount,
    'unique_source_text_count' => count($sourceIndex),
    'missing_source_text_count' => count($missing),
    'missing' => array_values($missing),
];
$candidate = p114d3_candidate_catalog($missing, $languages);
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents($candidatePath, p114d3_php_array_file($candidate));

$summary = [
    'P114D3_REFBOOK_DOC_I18N_MISSING_EXTRACTOR_OK',
    'ASAP_ROOT=' . $root,
    'LANGUAGES=' . implode(',', $languages),
    'CLASS_COUNT=' . (string) $classCount,
    'METHOD_COUNT=' . (string) $methodCount,
    'UNIQUE_SOURCE_TEXTS=' . (string) count($sourceIndex),
    'MISSING_SOURCE_TEXTS=' . (string) count($missing),
    'REPORT=' . $reportPath,
    'CANDIDATES=' . $candidatePath,
];
file_put_contents($summaryPath, implode(PHP_EOL, $summary) . PHP_EOL);
foreach ($summary as $line) { echo $line . PHP_EOL; }
