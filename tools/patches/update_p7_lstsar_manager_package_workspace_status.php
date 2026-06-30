<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$statusPath = $root . '/DOC/WORKSPACE_STATUS.md';
$marker = 'P7_LSTSAR_MANAGER_PACKAGE_CORE';
$block = <<<MD

## {$marker}

Status: pending commit after smoke.
Latest functional commit: `pending commit after P7_LSTSAR_MANAGER_PACKAGE_CORE smoke`
Tag target: `OPUS_P7_LSTSAR_MANAGER_PACKAGE_CORE`
Scope:
- creates `packages/opus-lstsar-manager/`;
- adds protected OPUS LSTSAR Manager application package;
- declares source ODBC, destination ODBC, source model, destination model, mappings and policies;
- exposes Securize / Transform / Store / Archive / Report declaration surfaces;
- allows dry-run preview only;
- forbids raw SQL, DDL and direct execution routes.

MD;

if (!is_file($statusPath)) {
    throw new RuntimeException('WORKSPACE_STATUS_NOT_FOUND');
}

$text = file_get_contents($statusPath);
if ($text === false) {
    throw new RuntimeException('WORKSPACE_STATUS_READ_FAILED');
}

if (!str_contains($text, $marker)) {
    $text = rtrim($text) . "\n" . $block;
}

if (file_put_contents($statusPath, $text) === false) {
    throw new RuntimeException('WORKSPACE_STATUS_WRITE_FAILED');
}

echo "WORKSPACE_STATUS_UPDATED\n";
