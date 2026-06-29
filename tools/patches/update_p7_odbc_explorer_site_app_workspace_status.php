<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/DOC/WORKSPACE_STATUS.md';
if (!is_file($path)) {
    throw new RuntimeException('WORKSPACE_STATUS_NOT_FOUND: ' . $path);
}

$text = (string) file_get_contents($path);
$text = preg_replace('/- Latest validated milestone: `[^`]+`/', '- Latest validated milestone: `P7_ODBC_EXPLORER_SITE_APP_CORE`', $text, 1) ?? $text;
$text = preg_replace('/- Latest functional commit: `[^`]+`/', '- Latest functional commit: `pending commit after P7_ODBC_EXPLORER_SITE_APP_CORE smoke`', $text, 1) ?? $text;
$text = preg_replace('/- Previous validated milestone: `[^`]+`/', '- Previous validated milestone: `P7_ODBC_EXPLORER_READONLY_CORE`', $text, 1) ?? $text;
$text = preg_replace('/- Previous cleanup commit: `[^`]+`/', '- Previous cleanup commit: `d40300d`', $text, 1) ?? $text;

if (!str_contains($text, '- `P7_ODBC_EXPLORER_READONLY_CORE`: OK')) {
    $text = str_replace(
        '- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.',
        '- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.' . PHP_EOL .
        '- `P7_OPUS_APP_PACKAGE_CONTRACT_CORE`: OK in source. Official OPUS applications are Composer-installable packages.' . PHP_EOL .
        '- `P7_OPUS_PACKAGES_DIRECTORY_CONTRACT_CORE`: OK in source. Official OPUS application packages live under `packages/` during monorepo development.' . PHP_EOL .
        '- `P7_ODBC_EXPLORER_READONLY_CORE`: OK in source. ODBC Explorer read-only catalog, table inspection, preview, TableModel and LSTSAR draft core are validated.',
        $text
    );
}

if (!str_contains($text, '- `P7_ODBC_EXPLORER_SITE_APP_CORE`: pending smoke')) {
    $text = str_replace(
        '- `P7_ODBC_EXPLORER_READONLY_CORE`: OK in source. ODBC Explorer read-only catalog, table inspection, preview, TableModel and LSTSAR draft core are validated.',
        '- `P7_ODBC_EXPLORER_READONLY_CORE`: OK in source. ODBC Explorer read-only catalog, table inspection, preview, TableModel and LSTSAR draft core are validated.' . PHP_EOL .
        '- `P7_ODBC_EXPLORER_SITE_APP_CORE`: pending smoke. ODBC Manager package gains protected site routes, controllers, ScoreTemplate views, I18N, ACL and navigation.',
        $text
    );
}

$text = preg_replace(
    '/## Next recommended milestones\R(?:\R|.)*?## Operational rule/s',
    "## Next recommended milestones" . PHP_EOL . PHP_EOL .
    "1. `P7_ODBC_EXPLORER_CRUD_CONTRACT_CORE`: define guarded insert/update/delete contracts, commands, results, capability checks and audit requirements." . PHP_EOL .
    "2. `P7_ODBC_EXPLORER_CRUD_CORE`: implement guarded ODBC prepared insert/update/delete through Model validation." . PHP_EOL .
    "3. `P7_ODBC_EXPLORER_CRUD_UI_CORE`: add protected CRUD UI forms to `packages/opus-odbc-manager`." . PHP_EOL .
    "4. `P7_ODBC_SCHEMA_BUILDER_CORE`: add Model-to-DDL dry-run, guarded DDL execution and driver capability checks." . PHP_EOL .
    "5. `P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE`: align LSTSAR with OPUS Model + ODBC for heterogeneous database table ingestion and storage." . PHP_EOL . PHP_EOL .
    "## Operational rule",
    $text
) ?? $text;

file_put_contents($path, $text);
echo "WORKSPACE_STATUS_UPDATED\n";
