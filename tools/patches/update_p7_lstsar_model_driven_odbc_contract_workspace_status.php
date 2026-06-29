<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/DOC/WORKSPACE_STATUS.md';
$block = <<<'MD'

## P7_LSTSAR_MODEL_DRIVEN_ODBC_CONTRACT_CORE

Status: validated locally after smoke.
Latest functional commit: `pending commit after P7_LSTSAR_MODEL_DRIVEN_ODBC_CONTRACT_CORE smoke`
Tag target: `OPUS_P7_LSTSAR_MODEL_DRIVEN_ODBC_CONTRACT_CORE`

Scope:
- LSTSAR canonical meaning is now Load / Securize / Transform / Store / Archive / Report.
- Six explicit historical stage files are populated: `01_Load.php`, `02_Secure.php`, `03_Transform.php`, `04_Store.php`, `05_Archive.php`, `06_Report.php`.
- `LstsarEngine` exposes a six-stage class catalog while preserving the previous `process()` API.
- Model-driven ODBC configuration declares source datasource/model, destination datasource/model, mapping, transform, security, archive and report policies.
- Backoffice declaration contract is prepared for a future LSTSAR Manager package.
- No heavy ODBC execution is introduced in this contract milestone.

MD;

$current = is_file($path) ? (string) file_get_contents($path) : "# OPUS Workspace Status\n";
$marker = '## P7_LSTSAR_MODEL_DRIVEN_ODBC_CONTRACT_CORE';
if (str_contains($current, $marker)) {
    $current = preg_replace('/\n## P7_LSTSAR_MODEL_DRIVEN_ODBC_CONTRACT_CORE\n.*?(?=\n## |\z)/s', $block, $current) ?? $current;
} else {
    $current = rtrim($current) . "\n" . $block;
}
file_put_contents($path, $current);
echo "WORKSPACE_STATUS_UPDATED\n";
