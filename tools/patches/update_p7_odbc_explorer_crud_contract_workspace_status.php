<?php
declare(strict_types=1);

$path = getcwd() . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'WORKSPACE_STATUS.md';
if (!is_file($path)) {
    throw new RuntimeException('WORKSPACE_STATUS_NOT_FOUND: ' . $path);
}

$text = (string) file_get_contents($path);
$text = preg_replace('/- Latest validated milestone: `[^`]+`/', '- Latest validated milestone: `P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE`', $text, 1);
$text = preg_replace('/- Latest functional commit: `[^`]+`/', '- Latest functional commit: `pending commit after P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE smoke`', $text, 1);
$text = preg_replace('/- Previous validated milestone: `[^`]+`/', '- Previous validated milestone: `P7_ODBC_EXPLORER_SITE_APP_CORE`', $text, 1);
$text = preg_replace('/- Previous cleanup commit: `[^`]+`/', '- Previous cleanup commit: `7c8b609`', $text, 1);

if (!str_contains($text, '`P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE`: OK in source.')) {
    $text = str_replace(
        '- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.' . PHP_EOL,
        '- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.' . PHP_EOL .
        '- `P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE`: OK in source. Guarded CRUD contract is defined for INSERT/UPDATE/DELETE through TableModel, ModelRecord validation, structured predicates, capability checks, ACL, confirmation and audit preview; no write execution or UI CRUD is exposed yet.' . PHP_EOL,
        $text
    );
}

$text = str_replace(
    '1. `P7_OPUS_APP_PACKAGE_CONTRACT_CORE`: implement real read-only ODBC explorer capabilities: drivers/DSN inventory, connection test, list tables, inspect columns, preview rows, generate TableModel and LSTSAR draft.' . PHP_EOL .
    '2. `P7_ODBC_EXPLORER_READONLY_CORE`: create the OPUS ODBC Explorer as a true OPUS site/application with routes, controllers, ScoreTemplate views, I18N, SSO/ACL and navigation.' . PHP_EOL .
    '3. `P7_ODBC_EXPLORER_SITE_APP_CORE`: add guarded insert/update/delete through Model validation and explicit confirmation.' . PHP_EOL .
    '4. `P7_ODBC_SCHEMA_BUILDER_CORE`: add Model-to-DDL dry-run, guarded DDL execution and driver capability checks.' . PHP_EOL .
    '5. `P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE`: align LSTSAR with OPUS Model + ODBC for heterogeneous database table ingestion and storage.',
    '1. `P7_ODBC_EXPLORER_CRUD_CORE`: execute guarded prepared ODBC INSERT/UPDATE/DELETE behind the CRUD contract.' . PHP_EOL .
    '2. `P7_ODBC_EXPLORER_CRUD_UI_CORE`: expose guarded CRUD forms in `packages/opus-odbc-manager`.' . PHP_EOL .
    '3. `P7_ODBC_MODEL_REFINEMENT_CORE`: refine Model metadata needed by CRUD and heterogeneous ODBC drivers.' . PHP_EOL .
    '4. Pause and tell the user before starting `P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE`.' . PHP_EOL .
    '5. `P7_ODBC_SCHEMA_BUILDER_CORE`: add Model-to-DDL dry-run, guarded DDL execution and driver capability checks.',
    $text
);

file_put_contents($path, $text);
echo "WORKSPACE_STATUS_UPDATED\n";
