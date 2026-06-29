<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$status = $root . '/DOC/WORKSPACE_STATUS.md';
$marker = 'P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE';
$block = <<<MD

## {$marker}

Status: validated locally before commit.

Latest functional commit: `pending commit after P7_LSTSAR_MODEL_DRIVEN_ODBC_CORE smoke`

Scope:

- real six-stage LSTSAR model-driven ODBC engine;
- Load / Securize / Transform / Store / Archive / Report stages preserved;
- ODBC source reader boundary;
- ODBC destination writer boundary;
- destination writer can use guarded ODBC CRUD service;
- deterministic in-memory readers/writers for smokes;
- archive/report first-class stage outputs.

MD;

$current = is_file($status) ? (string) file_get_contents($status) : "# OPUS workspace status\n";
if (!str_contains($current, "## {$marker}")) {
    file_put_contents($status, rtrim($current) . "\n" . $block);
}

echo "WORKSPACE_STATUS_UPDATED\n";
