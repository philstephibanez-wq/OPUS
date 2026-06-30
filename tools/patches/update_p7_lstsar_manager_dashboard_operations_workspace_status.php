<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$status = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'WORKSPACE_STATUS.md';
if (!is_file($status)) {
    fwrite(STDERR, "WORKSPACE_STATUS_NOT_FOUND\n");
    exit(1);
}
$text = (string) file_get_contents($status);
$marker = '## P7_LSTSAR_MANAGER_DASHBOARD_OPERATIONS_CORE';
$section = <<<'MD'

## P7_LSTSAR_MANAGER_DASHBOARD_OPERATIONS_CORE

Status: smoke-ready.
Latest functional commit: `pending commit after P7_LSTSAR_MANAGER_DASHBOARD_OPERATIONS_CORE smoke`
Scope:
- Site/client-scoped LSTSAR operations dashboard.
- Operation list with active/status/source/destination/mapping/assignments coverage.
- Last dry-run, last run, next planned run, archive/report/declaration links.
- Dry-run remains allowed.
- Manual launch, scheduler launch, raw SQL and DDL remain disabled.

MD;
if (!str_contains($text, $marker)) {
    $text = rtrim($text) . $section;
    file_put_contents($status, $text . "\n");
}

echo "WORKSPACE_STATUS_UPDATED\n";
