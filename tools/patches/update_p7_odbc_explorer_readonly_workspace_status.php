<?php
declare(strict_types=1);

$path = __DIR__ . '/../../DOC/WORKSPACE_STATUS.md';
if (!is_file($path)) {
    fwrite(STDERR, 'WORKSPACE_STATUS_NOT_FOUND' . PHP_EOL);
    exit(1);
}

$text = (string) file_get_contents($path);
$text = preg_replace('/Latest validated milestone: `[^`]+`/', 'Latest validated milestone: `P7_ODBC_EXPLORER_READONLY_CORE`', $text, 1) ?? $text;
$text = preg_replace('/Latest functional commit: `[^`]+`/', 'Latest functional commit: `pending commit after P7_ODBC_EXPLORER_READONLY_CORE smoke`', $text, 1) ?? $text;
$text = preg_replace('/Previous validated milestone: `[^`]+`/', 'Previous validated milestone: `P7_OPUS_PACKAGES_DIRECTORY_CONTRACT_CORE`', $text, 1) ?? $text;
$text = preg_replace('/Previous cleanup commit: `[^`]+`/', 'Previous cleanup commit: `6df37d4`', $text, 1) ?? $text;

$line = '- `P7_ODBC_EXPLORER_READONLY_CORE`: OK in source. Read-only ODBC Explorer service validates datasource overview, table listing, column inspection, row preview and LSTSAR draft generation without CRUD/DDL.';
if (!str_contains($text, '`P7_ODBC_EXPLORER_READONLY_CORE`')) {
    $text = str_replace("- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.\n", "- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.\n" . $line . "\n", $text);
}

if (!str_contains($text, 'packages/logandplay-portal')) {
    $text = str_replace('- OPUS ODBC Explorer must be a standalone OPUS site/application, not only a utility class.', '- OPUS ODBC Explorer must be a standalone OPUS site/application, not only a utility class.' . "\n" . '- The future LogAndPlay portal must also be delivered as a Composer-installable OPUS package under `packages/logandplay-portal`.', $text);
}

file_put_contents($path, $text);
echo 'WORKSPACE_STATUS_UPDATED' . PHP_EOL;
