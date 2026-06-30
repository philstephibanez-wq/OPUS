<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$statusPath = $root . '/DOC/WORKSPACE_STATUS.md';
if (!is_file($statusPath)) {
    throw new RuntimeException('WORKSPACE_STATUS_NOT_FOUND');
}

$status = (string) file_get_contents($statusPath);
$section = <<<'MD'

## P7_LSTSAR_MANAGER_DRY_RUN_INTEGRATION_CORE

Status: applied locally, pending smoke/commit.
Latest functional commit: `pending commit after P7_LSTSAR_MANAGER_DRY_RUN_INTEGRATION_CORE smoke`
Tag target: `OPUS_P7_LSTSAR_MANAGER_DRY_RUN_INTEGRATION_CORE`

Scope:
- connect LSTSAR Manager dry-run to `LstsarModelDrivenOdbcEngine`;
- keep direct execution forbidden;
- keep raw SQL and DDL forbidden;
- simulate source/destination/archive with in-memory ODBC boundaries;
- expose run result/report/stages in the dry-run view-model.
MD;

if (!str_contains($status, '## P7_LSTSAR_MANAGER_DRY_RUN_INTEGRATION_CORE')) {
    $status .= $section . PHP_EOL;
}

file_put_contents($statusPath, $status);
echo "WORKSPACE_STATUS_UPDATED
";
