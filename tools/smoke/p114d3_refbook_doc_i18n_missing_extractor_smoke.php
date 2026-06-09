<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$extractor = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . 'p114d3_refbook_doc_i18n_missing_extractor.php';
$tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'P114D3_REFBOOK_DOC_I18N_SMOKE_' . getmypid();
$failures = [];

function p114d3_smoke_assert(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

if (is_dir($tempRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($tempRoot);
}

$cmd = PHP_BINARY . ' ' . escapeshellarg($extractor) . ' --output-root ' . escapeshellarg($tempRoot);
$output = [];
$exitCode = 0;
exec($cmd, $output, $exitCode);

p114d3_smoke_assert($exitCode === 0, 'extractor exit code is ' . (string) $exitCode, $failures);
$text = implode("\n", $output);
p114d3_smoke_assert(str_contains($text, 'P114D3_REFBOOK_DOC_I18N_MISSING_EXTRACTOR_OK'), 'extractor OK marker missing', $failures);
p114d3_smoke_assert(is_file($tempRoot . DIRECTORY_SEPARATOR . 'refbook_doc_i18n_missing_source_texts.json'), 'missing JSON report', $failures);
p114d3_smoke_assert(is_file($tempRoot . DIRECTORY_SEPARATOR . 'refbook_doc_i18n_candidate_catalog.php'), 'missing candidate PHP catalog', $failures);
p114d3_smoke_assert(is_file($tempRoot . DIRECTORY_SEPARATOR . 'refbook_doc_i18n_summary.txt'), 'missing summary', $failures);

$report = json_decode((string) file_get_contents($tempRoot . DIRECTORY_SEPARATOR . 'refbook_doc_i18n_missing_source_texts.json'), true);
p114d3_smoke_assert(is_array($report), 'report JSON is invalid', $failures);
p114d3_smoke_assert(($report['schema'] ?? '') === 'P114D3_REFBOOK_DOC_I18N_MISSING_SOURCE_TEXTS_V1', 'report schema invalid', $failures);
p114d3_smoke_assert((int) ($report['class_count'] ?? 0) > 0, 'class count is zero', $failures);
p114d3_smoke_assert((int) ($report['unique_source_text_count'] ?? 0) > 0, 'unique source text count is zero', $failures);
p114d3_smoke_assert(str_contains((string) file_get_contents($tempRoot . DIRECTORY_SEPARATOR . 'refbook_doc_i18n_candidate_catalog.php'), 'TODO_TRANSLATE[') || (int) ($report['missing_source_text_count'] ?? 0) === 0, 'candidate catalog has no TODO markers despite missing entries', $failures);

if ($failures !== []) {
    echo "P114D3_REFBOOK_DOC_I18N_MISSING_EXTRACTOR_SMOKE_FAIL\n";
    foreach ($failures as $idx => $failure) {
        echo str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT) . ' ' . $failure . "\n";
    }
    exit(1);
}

echo "P114D3_REFBOOK_DOC_I18N_MISSING_EXTRACTOR_SMOKE_OK\n";
