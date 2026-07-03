<?php
declare(strict_types=1);

echo 'OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_NO_WARNING_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$audit = $root . '/tools/audits/audit_opus_manager_opus_only_realignment_core.php';
$report = $root . '/DOC/OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_AUDIT.md';

foreach ([$audit, $report] as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('FILE_MISSING: ' . $file);
    }
}

$auditSource = file_get_contents($audit);
$reportSource = file_get_contents($report);

if (!is_string($auditSource) || !str_contains($auditSource, "'\$html = '")) {
    throw new RuntimeException('AUDIT_LITERAL_HTML_ASSIGN_MISSING');
}

if (!is_string($auditSource) || !str_contains($auditSource, "'\$html .='")) {
    throw new RuntimeException('AUDIT_LITERAL_HTML_APPEND_MISSING');
}

if (!is_string($reportSource) || !str_contains($reportSource, 'OPUS_ONLY_AUDIT_VIOLATIONS_PRESENT')) {
    throw new RuntimeException('REPORT_STATUS_MISSING');
}

echo 'CHECK_OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_NO_WARNING=OK' . PHP_EOL;
echo 'OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_NO_WARNING_CORE_SMOKE_OK' . PHP_EOL;
