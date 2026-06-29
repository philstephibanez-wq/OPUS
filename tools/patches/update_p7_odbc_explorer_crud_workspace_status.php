<?php
declare(strict_types=1);

$path = getcwd() . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'WORKSPACE_STATUS.md';
if (!is_file($path)) {
    fwrite(STDERR, "WORKSPACE_STATUS_NOT_FOUND\n");
    exit(1);
}

$text = (string) file_get_contents($path);
$text = preg_replace('/- Latest validated milestone: `[^`]+`/', '- Latest validated milestone: `P7_ODBC_EXPLORER_CRUD_CORE`', $text, 1) ?? $text;
$text = preg_replace('/- Latest functional commit: `[^`]+`/', '- Latest functional commit: `pending commit after P7_ODBC_EXPLORER_CRUD_CORE smoke`', $text, 1) ?? $text;
$text = preg_replace('/- Previous validated milestone: `[^`]+`/', '- Previous validated milestone: `P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE`', $text, 1) ?? $text;
$text = preg_replace('/- Previous cleanup commit: `[^`]+`/', '- Previous cleanup commit: `e413ce6`', $text, 1) ?? $text;

if (!str_contains($text, '- `P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE`: OK in source.')) {
    $text = str_replace(
        "- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.\n",
        "- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.\n- `P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE`: OK in source. Guarded CRUD command contracts, capabilities, predicates, Model validation, audit preview and confirmation requirements are validated.\n",
        $text
    );
}

if (!str_contains($text, '- `P7_ODBC_EXPLORER_CRUD_CORE`: OK in source.')) {
    $text = str_replace(
        "- `P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE`: OK in source. Guarded CRUD command contracts, capabilities, predicates, Model validation, audit preview and confirmation requirements are validated.\n",
        "- `P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE`: OK in source. Guarded CRUD command contracts, capabilities, predicates, Model validation, audit preview and confirmation requirements are validated.\n- `P7_ODBC_EXPLORER_CRUD_CORE`: OK in source. Structured INSERT/UPDATE/DELETE SQL plans and native prepared ODBC execution service are validated; no CRUD UI and no DDL yet.\n",
        $text
    );
}

if (!str_contains($text, '- ODBC CRUD execution must use prepared statements.')) {
    $text = str_replace(
        "- Destructive CRUD and DDL operations require explicit guards, dry-run where applicable, non-empty predicates, confirmation and audit-oriented design.\n",
        "- Destructive CRUD and DDL operations require explicit guards, dry-run where applicable, non-empty predicates, confirmation and audit-oriented design.\n- ODBC CRUD execution must use prepared statements and must never interpolate user values into SQL.\n",
        $text
    );
}

$text = preg_replace('/1\. `[^`]+`: .*\n2\. `[^`]+`: .*\n3\. `[^`]+`: .*\n4\. `[^`]+`: .*\n5\. `[^`]+`: .*/s', "1. `P7_ODBC_EXPLORER_CRUD_UI_CORE`: expose guarded insert/update/delete through OPUS ODBC Manager routes, controllers, ScoreTemplate forms and profiler events.\n2. `P7_ODBC_MODEL_REFINEMENT_CORE`: refine Model validation for required fields, identity columns and driver metadata.\n3. `P7_ODBC_SCHEMA_BUILDER_CORE`: add Model-to-DDL dry-run, guarded DDL execution and driver capability checks.\n4. Pause and tell the user before returning to `P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE`.", $text, 1) ?? $text;

file_put_contents($path, $text);
echo "WORKSPACE_STATUS_UPDATED\n";
