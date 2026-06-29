<?php
declare(strict_types=1);

$path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'WORKSPACE_STATUS.md';
if (!is_file($path)) {
    echo "WORKSPACE_STATUS_SKIPPED\n";
    exit(0);
}
$txt = (string) file_get_contents($path);
$txt = preg_replace('/Latest validated milestone: `[^`]+`/', 'Latest validated milestone: `P7_OPUS_PACKAGES_DIRECTORY_CONTRACT_CORE`', $txt, 1);
$txt = preg_replace('/Latest functional commit: `[^`]+`/', 'Latest functional commit: `pending commit after P7_OPUS_PACKAGES_DIRECTORY_CONTRACT_CORE smoke`', $txt, 1);
if (strpos($txt, '`P7_OPUS_PACKAGES_DIRECTORY_CONTRACT_CORE`') === false) {
    $needle = "- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.\n";
    $insert = $needle . "- `P7_OPUS_PACKAGES_DIRECTORY_CONTRACT_CORE`: pending smoke. Official OPUS applications live as Composer-installable packages under `packages/`, including RefBook, demo and ODBC Manager.\n";
    $txt = str_replace($needle, $insert, $txt);
}
if (strpos($txt, 'official OPUS application source packages') === false) {
    $needle = "- OPUS ODBC Explorer must be a standalone OPUS site/application, not only a utility class.\n";
    $insert = $needle . "- `packages/` is the official monorepo source directory for OPUS application packages; Composer remains the official installation path.\n";
    $txt = str_replace($needle, $insert, $txt);
}
file_put_contents($path, $txt);
echo "WORKSPACE_STATUS_UPDATED\n";
