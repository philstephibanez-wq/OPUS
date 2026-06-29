<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$statusFile = $root . '/DOC/WORKSPACE_STATUS.md';
if (!is_file($statusFile)) {
    throw new RuntimeException('WORKSPACE_STATUS_MISSING');
}

$status = (string) file_get_contents($statusFile);
$status = preg_replace('/Latest validated milestone: `[^`]+`/', 'Latest validated milestone: `P7_ODBC_MODEL_REFINEMENT_CORE`', $status) ?? $status;
$status = preg_replace('/Latest functional commit: `[^`]+`/', 'Latest functional commit: `pending commit after P7_ODBC_MODEL_REFINEMENT_CORE smoke`', $status) ?? $status;
$status = preg_replace('/Previous validated milestone: `[^`]+`/', 'Previous validated milestone: `P7_ODBC_EXPLORER_CRUD_UI_CORE`', $status) ?? $status;
$status = preg_replace('/Previous cleanup commit: `[^`]+`/', 'Previous cleanup commit: `b9f47d9`', $status) ?? $status;

$marker = '- `P7_ODBC_MODEL_REFINEMENT_CORE`: OK in source. OPUS Model exposes table identity, field write profiles, mutation validation reports and insert/update/delete model validation for ODBC CRUD and future Model-driven LSTSAR.';
if (!str_contains($status, $marker)) {
    $status = str_replace(
        '- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.',
        '- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.' . PHP_EOL . $marker,
        $status
    );
}

$note = '- OPUS Model now carries explicit write profiles and mutation validation reports before ODBC CRUD or future LSTSAR storage executes writes.';
if (!str_contains($status, $note)) {
    $status = str_replace(
        '- Destructive CRUD and DDL operations require explicit guards, dry-run where applicable, non-empty predicates, confirmation and audit-oriented design.',
        '- Destructive CRUD and DDL operations require explicit guards, dry-run where applicable, non-empty predicates, confirmation and audit-oriented design.' . PHP_EOL . $note,
        $status
    );
}

$pause = '- Pause before `P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE`: ODBC CRUD + Model must be announced as finished before restarting LSTSAR.';
if (!str_contains($status, $pause)) {
    $status .= PHP_EOL . '## LSTSAR pause rule' . PHP_EOL . PHP_EOL . $pause . PHP_EOL;
}

file_put_contents($statusFile, $status);
echo "WORKSPACE_STATUS_UPDATED\n";
